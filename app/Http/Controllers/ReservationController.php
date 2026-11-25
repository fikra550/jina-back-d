<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Matricule;
use App\Models\Client;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function index(): JsonResponse
    {
        $reservations = Reservation::with(['car', 'client', 'secondDriverClient', 'matricule' ,'sousLocation'])->get();
        
        foreach ($reservations as $reservation) {
            if ($reservation->status === 'confirmed') {
                $endDate = \Carbon\Carbon::parse($reservation->end_date);
                $today = \Carbon\Carbon::today();
                if ($endDate->lt($today)) {
                    $reservation->update(['status' => 'retard']);
                }
            }
        }
        
        $reservations = Reservation::with(['car', 'client', 'secondDriverClient', 'matricule' ,'sousLocation'])->get();
        return response()->json(['reservations' => $reservations]);
    }

    public function checkLate(): JsonResponse
    {
        $today = \Carbon\Carbon::today();
        $lateReservations = Reservation::where('status', 'confirmed')
            ->whereDate('end_date', '<', $today)
            ->get();
            
        $updatedCount = 0;
        foreach ($lateReservations as $reservation) {
            $reservation->update(['status' => 'retard']);
            $updatedCount++;
        }
        return response()->json([
            'message' => "{$updatedCount} reservations marked as late",
            'updated_count' => $updatedCount
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('Reservation store request received', $request->all());

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'rental_days' => 'nullable|integer|min:1',
            'total_price' => 'required|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'remaining_amount' => 'sometimes|numeric|min:0',
            'payment_history' => 'nullable|array',
            'status' => 'sometimes|in:pending,confirmed,cancelled,contacted,completed,retard',
            'matricule_id' => 'nullable|exists:matricules,id',
            'car_id' => 'required|exists:cars,id',
            'client_id' => 'required|exists:clients,id',
            'has_second_driver' => 'sometimes|boolean',
            'second_driver_client_id' => 'nullable|required_if:has_second_driver,true|exists:clients,id',
            'notes' => 'nullable|string',
            'reception_notes' => 'nullable|string',
            'kilometrage_sortie' => 'nullable|numeric|min:0',
            'kilometrage_entree' => 'nullable|numeric|min:0',
            'sous_location_id' => 'nullable|exists:sous_locations,id',
            'can_extend_days' => 'sometimes|boolean',
            'prolongation_days' => 'nullable|integer|min:0',
        ]);

        Log::info('Validation passed', $validated);

        if (!empty($validated['matricule_id'])) {
            $matricule = Matricule::find($validated['matricule_id']);
            if ($matricule && $matricule->status === Matricule::STATUS_SOLD) {
                return response()->json([
                    'message' => 'Ce matricule est vendu et ne peut pas être utilisé pour une réservation.'
                ], 422);
            }
        }

        if (empty($validated['rental_days']) && !empty($validated['end_date'])) {
            $start = \Carbon\Carbon::parse($validated['start_date']);
            $end = \Carbon\Carbon::parse($validated['end_date']);
            $validated['rental_days'] = $end->diffInDays($start) + 1;
        } elseif (empty($validated['end_date']) && !empty($validated['rental_days'])) {
            $start = \Carbon\Carbon::parse($validated['start_date']);
            $validated['end_date'] = $start->copy()->addDays($validated['rental_days'] - 1);
        }

        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $today = \Carbon\Carbon::today();
        if (isset($validated['status']) && $validated['status'] === 'confirmed' && $endDate->lt($today)) {
            $validated['status'] = 'retard';
        }

        $validated['total_days'] = $validated['rental_days'] ?? 1;
        $validated['amount_paid'] = $validated['amount_paid'] ?? 0;
        $validated['remaining_amount'] = $validated['remaining_amount'] ?? $validated['total_price'];
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['has_second_driver'] = $validated['has_second_driver'] ?? false;
        $validated['prolongation_days'] = $validated['prolongation_days'] ?? 0;

        $user = auth()->user();
        $validated['created_by'] = $user ? ($user->Fullname ?? $user->name ?? $user->username ?? 'System User') : 'System';

        if ($validated['has_second_driver']) {
            if (empty($validated['second_driver_client_id'])) {
                return response()->json([
                    'message' => 'Veuillez sélectionner un deuxième conducteur'
                ], 422);
            }
            if ($validated['second_driver_client_id'] == $validated['client_id']) {
                return response()->json([
                    'message' => 'Le deuxième conducteur ne peut pas être le même que le locataire'
                ], 422);
            }
        } else {
            $validated['second_driver_client_id'] = null;
        }

        if (!empty($validated['matricule_id'])) {
            $matricule = Matricule::find($validated['matricule_id']);
            if ($matricule) {
                $validated['matricule_kilometrage_at_start'] = $matricule->kilometrage;
                if (empty($validated['kilometrage_sortie'])) {
                    $validated['kilometrage_sortie'] = $matricule->kilometrage;
                }
            }
        }

        if (!empty($validated['amount_paid']) && $validated['amount_paid'] > 0 && empty($validated['payment_history'])) {
            $validated['payment_history'] = [[
                'id' => uniqid(),
                'amount' => (float) $validated['amount_paid'],
                'date' => now()->toDateString(),
                'method' => 'cash',
                'notes' => 'Paiement initial',
                'created_at' => now()->toDateTimeString()
            ]];
        } elseif (empty($validated['payment_history'])) {
            $validated['payment_history'] = [];
        }

        if (!empty($validated['payment_history'])) {
            $totalFromHistory = array_sum(array_column($validated['payment_history'], 'amount'));
            $validated['amount_paid'] = $totalFromHistory;
            $validated['remaining_amount'] = (float) $validated['total_price'] - (float) $totalFromHistory;
        }

        Log::info('Final data before creation', $validated);

        try {
            DB::beginTransaction();

            $reservation = Reservation::create($validated);

            // Generate contract number and signature tokens if confirmed
            if ($reservation->status === 'confirmed') {
                $contract = Reservation::generateContractNumber();
                $reservation->contract_number = $contract['number'];
                $reservation->contract_year   = $contract['year'];
                $this->generateSignatureTokens($reservation);
            }

            // Update matricule status if needed
            if ($reservation->matricule_id) {
                $matricule = Matricule::find($reservation->matricule_id);
                if ($matricule) {
                    if ($reservation->status === 'confirmed' || $reservation->status === 'retard') {
                        $matricule->update(['status' => 'inactive']);
                    } elseif ($reservation->status === 'completed') {
                        $matricule->update(['status' => 'active']);
                    }
                }
            }

            DB::commit();
            Log::info('Reservation created successfully', ['reservation_id' => $reservation->id]);
            return response()->json(['reservation' => $reservation->load(['car', 'client', 'secondDriverClient', 'matricule' ,'sousLocation'])], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reservation creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create reservation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        Log::info('Reservation update request received', [
            'reservation_id' => $id,
            'request_data' => $request->all()
        ]);

        $reservation = Reservation::findOrFail($id);
        $oldStatus = $reservation->status;
        $oldMatriculeId = $reservation->matricule_id;

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'rental_days' => 'sometimes|integer|min:1',
            'total_price' => 'sometimes|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'remaining_amount' => 'sometimes|numeric|min:0',
            'payment_history' => 'nullable|array',
            'status' => 'sometimes|in:pending,confirmed,cancelled,contacted,completed,retard',
            'matricule_id' => 'nullable|exists:matricules,id',
            'car_id' => 'sometimes|exists:cars,id',
            'client_id' => 'sometimes|exists:clients,id',
            'has_second_driver' => 'sometimes|boolean',
            'second_driver_client_id' => 'nullable|required_if:has_second_driver,true|exists:clients,id',
            'notes' => 'nullable|string',
            'reception_notes' => 'nullable|string',
            'kilometrage_sortie' => 'nullable|numeric|min:0',
            'kilometrage_entree' => 'nullable|numeric|min:0',
            'sous_location_id' => 'nullable|exists:sous_locations,id',
            'can_extend_days' => 'sometimes|boolean',
            'prolongation_days' => 'nullable|integer|min:0',
        ]);

        Log::info('Validation passed for update', $validated);

        // --- START PROLONGATION HANDLING ---
        if (isset($validated['can_extend_days']) && $validated['can_extend_days'] === true) {
            if (isset($validated['prolongation_days']) && $validated['prolongation_days'] > 0) {
                $newProlongation = (int) $validated['prolongation_days'];
                if (isset($validated['rental_days'])) {
                    $baseRentalDays = max((int) $validated['rental_days'] - $newProlongation, 1);
                } else {
                    $oldProlongation = (int) ($reservation->prolongation_days ?? 0);
                    $baseRentalDays = max((int) $reservation->rental_days - $oldProlongation, 1);
                }
                $startDate = \Carbon\Carbon::parse($validated['start_date'] ?? $reservation->start_date);
                $newEndDate = $startDate->copy()->addDays($baseRentalDays + $newProlongation);
                $validated['end_date'] = $newEndDate->toDateString();
                $validated['rental_days'] = $baseRentalDays + $newProlongation;
                $validated['prolongation_days'] = $newProlongation;
            } else {
                $validated['prolongation_days'] = $reservation->prolongation_days ?? 0;
            }
        } else {
            if (isset($validated['can_extend_days']) && $validated['can_extend_days'] === false) {
                $oldProlongation = (int) ($reservation->prolongation_days ?? 0);
                if ($oldProlongation > 0) {
                    $validated['end_date'] = \Carbon\Carbon::parse($reservation->end_date)->subDays($oldProlongation)->toDateString();
                    $validated['rental_days'] = max((int) $reservation->rental_days - $oldProlongation, 1);
                }
                $validated['prolongation_days'] = 0;
            }
        }
        // --- END PROLONGATION HANDLING ---

        if (isset($validated['matricule_id']) && $validated['matricule_id'] != $oldMatriculeId) {
            $matricule = Matricule::find($validated['matricule_id']);
            if ($matricule && $matricule->status === Matricule::STATUS_SOLD) {
                return response()->json([
                    'message' => 'Ce matricule est vendu et ne peut pas être utilisé pour une réservation.'
                ], 422);
            }
        }

        if (isset($validated['status']) && $validated['status'] === 'confirmed' && $oldStatus !== 'confirmed') {
            if (is_null($reservation->contract_number)) {
                $contract = Reservation::generateContractNumber();
                $validated['contract_number'] = $contract['number'];
                $validated['contract_year']   = $contract['year'];
            }
            // Generate signature tokens for both drivers
            $this->generateSignatureTokens($reservation);
        }

        // Auto reception notes for completion
        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $user = auth()->user();
            if ($user) {
                $userFullName = $user->Fullname ?? $user->name ?? 'Unknown User';
                $currentDate = now()->format('d/m/Y H:i');
                $receptionNote = "Réceptionné par: {$userFullName} le {$currentDate}";
                if (!empty($validated['kilometrage_entree'])) {
                    $departureKm = $reservation->kilometrage_sortie ?? 'N/A';
                    $receptionNote .= " | Kilométrage départ: {$departureKm} km, retour: {$validated['kilometrage_entree']} km";
                }
                $existingNotes = $reservation->reception_notes ?? '';
                if (!empty($existingNotes)) {
                    $receptionNote = "{$existingNotes}\n{$receptionNote}";
                }
                $validated['reception_notes'] = $receptionNote;
            }
        }

        if (isset($validated['end_date']) || isset($validated['status'])) {
            $endDate = isset($validated['end_date'])
                ? \Carbon\Carbon::parse($validated['end_date'])
                : \Carbon\Carbon::parse($reservation->end_date);
            $today = \Carbon\Carbon::today();
            $newStatus = $validated['status'] ?? $reservation->status;
            if ($newStatus === 'confirmed' && $endDate->lt($today)) {
                $validated['status'] = 'retard';
            }
        }

        if (isset($validated['rental_days']) && !isset($validated['end_date'])) {
            $startDate = $validated['start_date'] ?? $reservation->start_date;
            $start = \Carbon\Carbon::parse($startDate);
            $validated['end_date'] = $start->copy()->addDays($validated['rental_days'] - 1);
        } elseif (isset($validated['end_date']) && !isset($validated['rental_days'])) {
            $startDate = $validated['start_date'] ?? $reservation->start_date;
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($validated['end_date']);
            $validated['rental_days'] = $end->diffInDays($start) + 1;
        }

        if (isset($validated['rental_days'])) {
            $validated['total_days'] = $validated['rental_days'];
        }

        if (isset($validated['has_second_driver']) && $validated['has_second_driver']) {
            if (empty($validated['second_driver_client_id'])) {
                return response()->json([
                    'message' => 'Veuillez sélectionner un deuxième conducteur'
                ], 422);
            }
            if ($validated['second_driver_client_id'] == ($validated['client_id'] ?? $reservation->client_id)) {
                return response()->json([
                    'message' => 'Le deuxième conducteur ne peut pas être le même que le locataire'
                ], 422);
            }
        } elseif (isset($validated['has_second_driver']) && !$validated['has_second_driver']) {
            $validated['second_driver_client_id'] = null;
        }

        if (isset($validated['payment_history'])) {
            $totalFromHistory = array_sum(array_column($validated['payment_history'], 'amount'));
            $validated['amount_paid'] = $totalFromHistory;
        } else {
            $validated['payment_history'] = $reservation->payment_history;
        }

        $finalTotalPrice = $validated['total_price'] ?? $reservation->total_price;
        $finalAmountPaid = $validated['amount_paid'] ?? $reservation->amount_paid;
        $validated['remaining_amount'] = max((float) $finalTotalPrice - (float) $finalAmountPaid, 0);

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->handleMatriculeStatusChange($reservation, $validated['status'], $oldStatus, $oldMatriculeId);
        }

        if (isset($validated['matricule_id']) && $validated['matricule_id'] != $oldMatriculeId) {
            $this->handleMatriculeChange($reservation, $validated['matricule_id'], $oldMatriculeId, $validated['status'] ?? $reservation->status);
        }

        if (isset($validated['status']) && $validated['status'] === 'completed' &&
            !empty($validated['kilometrage_entree']) &&
            !empty($reservation->matricule_id)) {

            $matricule = Matricule::find($reservation->matricule_id);
            if ($matricule) {
                $departureKilometer = $reservation->kilometrage_sortie ?? $matricule->kilometrage;
                $returnKilometer = $validated['kilometrage_entree'];
                $shouldReset = false;
                if ($returnKilometer > $departureKilometer) {
                    $distanceParcourue = $returnKilometer - $departureKilometer;
                    $departureBase = floor($departureKilometer / 10000) * 10000;
                    $returnBase = floor($returnKilometer / 10000) * 10000;
                    if ($distanceParcourue >= 10000 || $returnBase > $departureBase) {
                        $shouldReset = true;
                    }
                }
                if ($shouldReset) {
                    $matricule->oil = 'no';
                    $matricule->filter_oil = 'no';
                    $matricule->vidange_status = 'not done';
                }
                $matricule->kilometrage = $returnKilometer;
                $matricule->save();
                $validated['matricule_kilometrage_at_end'] = $returnKilometer;
            }
        }

        unset($validated['created_by']);

        try {
            DB::beginTransaction();

            Log::info('Updating reservation with data', $validated);
            $reservation->update($validated);

            DB::commit();
            Log::info('Reservation updated successfully', ['reservation_id' => $reservation->id]);
            $reservation->refresh();
            return response()->json(['reservation' => $reservation->load(['car', 'client', 'secondDriverClient', 'matricule', 'sousLocation'])]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reservation update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update reservation: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleMatriculeStatusChange($reservation, $newStatus, $oldStatus, $oldMatriculeId)
    {
        $matriculeId = $reservation->matricule_id ?? $oldMatriculeId;
        if (!$matriculeId) return;
        $matricule = Matricule::find($matriculeId);
        if (!$matricule) return;

        if (($newStatus === 'confirmed' || $newStatus === 'retard') && 
            ($oldStatus !== 'confirmed' && $oldStatus !== 'retard')) {
            $matricule->update(['status' => 'inactive']);
        } elseif ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $matricule->update(['status' => 'active']);
        } elseif (($oldStatus === 'confirmed' || $oldStatus === 'retard') && 
                $newStatus !== 'confirmed' && $newStatus !== 'retard' && $newStatus !== 'completed') {
            $matricule->update(['status' => 'active']);
        } elseif ($oldStatus === 'completed' && ($newStatus === 'confirmed' || $newStatus === 'retard')) {
            $matricule->update(['status' => 'inactive']);
        }
    }

    private function handleMatriculeChange($reservation, $newMatriculeId, $oldMatriculeId, $status)
    {
        if ($oldMatriculeId) {
            $oldMatricule = Matricule::find($oldMatriculeId);
            if ($oldMatricule && ($reservation->status === 'confirmed' || $reservation->status === 'retard')) {
                $oldMatricule->update(['status' => 'active']);
            }
        }
        if ($newMatriculeId) {
            $newMatricule = Matricule::find($newMatriculeId);
            if ($newMatricule) {
                if ($status === 'confirmed' || $status === 'retard') {
                    $newMatricule->update(['status' => 'inactive']);
                } else {
                    $newMatricule->update(['status' => 'active']);
                }
            }
        }
    }

    public function destroy($id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);
        if ($reservation->matricule_id && ($reservation->status === 'confirmed' || $reservation->status === 'retard')) {
            $matricule = Matricule::find($reservation->matricule_id);
            if ($matricule) {
                $matricule->update(['status' => 'active']);
            }
        }
        $reservation->delete();
        return response()->json(['message' => 'Reservation deleted successfully']);
    }

    public function getContract($id): JsonResponse
    {
        $reservation = Reservation::with(['car', 'client', 'secondDriverClient', 'matricule'])->findOrFail($id);
        return response()->json([
            'reservation' => $reservation,
            'contract_date' => now()->format('d/m/Y'),
            'company_info' => [
                'name' => 'JINA BUSINESS',
                'address' => 'LOT ERRACHAD N° 706 TR 02 MAG 01 MEDIOUNA, CASABLANCA - Maroc',
                'rc' => '624023',
                'if' => '65912528',
                'tp' => '37701443',
                'cnss' => '5317601',
                'ice' => '003478448000020',
                'tel' => '06 63 04 95 75 - 06 67 47 47 45',
                'email' => 'jinabusiness0@gmail.com'
            ]
        ]);
    }

    // ---------- Signature Generation Helpers ----------
    private function generateSignatureCode(): string
    {
        do {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Reservation::where('signature_code', $code)->exists());
        return $code;
    }

    private function generateSignatureToken(): string
    {
        return Str::random(32);
    }

    /**
     * Generate signature tokens and codes for both locataire and second driver.
     */
    private function generateSignatureTokens($reservation)
    {
        // Locataire (existing logic)
        if (empty($reservation->signature_code)) {
            $reservation->signature_code = $this->generateSignatureCode();
        }
        if (empty($reservation->signature_token)) {
            $reservation->signature_token = $this->generateSignatureToken();
            $reservation->signature_expires_at = now()->addDays(7);
        }

        // Second driver (only if has_second_driver)
        if ($reservation->has_second_driver) {
            if (empty($reservation->signature_code_second_driver)) {
                $reservation->signature_code_second_driver = $this->generateSignatureCode();
            }
            if (empty($reservation->signature_token_second_driver)) {
                $reservation->signature_token_second_driver = $this->generateSignatureToken();
                $reservation->signature_expires_at_second_driver = now()->addDays(7);
            }
        }

        $reservation->save();
    }

    // ---------- Public Signature Endpoint ----------
    public function signContract(Request $request, $token)
    {
        // Find reservation by either token
        $reservation = Reservation::where('signature_token', $token)
                                  ->orWhere('signature_token_second_driver', $token)
                                  ->firstOrFail();

        // Determine which role this token belongs to
        $isSecondDriver = ($reservation->signature_token_second_driver === $token);
        $role = $isSecondDriver ? 'second' : 'locataire';

        // Check expiry for the appropriate token
        if ($isSecondDriver) {
            if ($reservation->signature_expires_at_second_driver && 
                $reservation->signature_expires_at_second_driver->isPast()) {
                return response()->json(['message' => 'Le lien de signature a expiré.'], 422);
            }
        } else {
            if ($reservation->signature_expires_at && $reservation->signature_expires_at->isPast()) {
                return response()->json(['message' => 'Le lien de signature a expiré.'], 422);
            }
        }

        $request->validate([
            'code'      => 'required|string',
            'signature' => 'required|string',
        ]);

        // Validate the correct code
        $expectedCode = $isSecondDriver 
            ? $reservation->signature_code_second_driver 
            : $reservation->signature_code;

        if ($request->code !== $expectedCode) {
            return response()->json(['message' => 'Code invalide.'], 422);
        }

        if ($reservation->status !== 'confirmed') {
            return response()->json(['message' => 'Cette réservation n\'est pas confirmée.'], 422);
        }

        $signatures = $reservation->signatures ?? [];

        if ($isSecondDriver) {
            $signatures['secondConducteur_image'] = $request->signature;
            $reservation->signature_token_second_driver = null;
            $reservation->signature_expires_at_second_driver = now();
        } else {
            $signatures['locataire_image'] = $request->signature;
            $reservation->signature_token = null;
            $reservation->signature_expires_at = now();
        }

        $reservation->signatures = $signatures;
        $reservation->save();

        return response()->json([
            'message'    => 'Signature ajoutée avec succès.',
            'signatures' => $reservation->signatures,
            'role'       => $role,
        ]);
    }

    // ---------- Admin endpoints for generating signature links ----------
    public function generateSignature(Request $request, $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Seules les réservations confirmées peuvent avoir un lien de signature.'
            ], 422);
        }

        $newCode = $this->generateSignatureCode();
        $newToken = $this->generateSignatureToken();

        $reservation->signature_code = $newCode;
        $reservation->signature_token = $newToken;
        $reservation->signature_expires_at = now()->addDays(7);
        $reservation->save();

        return response()->json([
            'signature_token' => $newToken,
            'signature_code'  => $newCode,
            'expires_at'      => $reservation->signature_expires_at,
        ]);
    }

    public function generateSecondDriverSignature($id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Seules les réservations confirmées peuvent avoir un lien de signature.'
            ], 422);
        }

        if (!$reservation->has_second_driver) {
            return response()->json([
                'message' => 'Cette réservation n\'a pas de deuxième conducteur.'
            ], 422);
        }

        $newCode = $this->generateSignatureCode();
        $newToken = $this->generateSignatureToken();

        $reservation->signature_code_second_driver = $newCode;
        $reservation->signature_token_second_driver = $newToken;
        $reservation->signature_expires_at_second_driver = now()->addDays(7);
        $reservation->save();

        return response()->json([
            'signature_token' => $newToken,
            'signature_code'  => $newCode,
            'expires_at'      => $reservation->signature_expires_at_second_driver,
        ]);
    }

    // ---------- Payment methods (new) ----------
    public function addPayment(Request $request, $id): JsonResponse
    {
        try {
            $reservation = Reservation::findOrFail($id);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'date'   => 'required|date',
                'method' => 'required|string|in:cash,check,card,transfer',
                'notes'  => 'nullable|string'
            ]);

            $reservation->addPayment(
                $validated['amount'],
                $validated['date'],
                $validated['method'],
                $validated['notes'] ?? ''
            );

            $reservation->save();

            return response()->json([
                'message' => 'Payment added successfully',
                'reservation' => $reservation->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add payment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to add payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removePayment(Request $request, $id): JsonResponse
    {
        try {
            $reservation = Reservation::findOrFail($id);

            $validated = $request->validate([
                'payment_id' => 'required|string'
            ]);

            $reservation->removePayment($validated['payment_id']);
            $reservation->save();

            return response()->json([
                'message' => 'Payment removed successfully',
                'reservation' => $reservation->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove payment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove payment: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
 * Get signature info (role, client name) for a given token.
 * This is used by the frontend to show who is signing.
 */
public function getSignInfo($token)
{
    $reservation = Reservation::where('signature_token', $token)
                              ->orWhere('signature_token_second_driver', $token)
                              ->firstOrFail();

    $isSecondDriver = ($reservation->signature_token_second_driver === $token);
    $role = $isSecondDriver ? 'second' : 'locataire';

    // Get the corresponding client
    $client = $isSecondDriver ? $reservation->secondDriverClient : $reservation->client;
    $clientName = $client ? $client->prenom . ' ' . $client->nom : null;

    // Optional vehicle info
    $car = $reservation->car;
    $carInfo = $car ? $car->brand . ' ' . $car->model : null;

    return response()->json([
        'role' => $role,
        'client_name' => $clientName,
        'reservation_id' => $reservation->id,
        'car' => $carInfo,
    ]);
}
}