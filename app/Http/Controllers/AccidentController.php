<?php

namespace App\Http\Controllers;

use App\Models\Accident;
use App\Models\Matricule;
use App\Models\Car;
use App\Models\Client;
use App\Models\Garage;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class AccidentController extends Controller
{
    public function index(): JsonResponse
    {
        $accidents = Accident::with(['matricule', 'car', 'client', 'garage', 'reservation'])->get();

        $accidents->each(function ($accident) {
            $accident->img_accident_urls = $accident->img_accident_urls;
            $accident->img_evaluation_expert_urls = $accident->img_evaluation_expert_urls;
            $accident->img_fixed_urls = $accident->img_fixed_urls;
            $accident->image_facture_urls = $accident->image_facture_urls;
        });

        return response()->json(['accidents' => $accidents]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'date_accident' => 'required|date',
                'time_accident' => 'nullable|date_format:H:i',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'police_report_number' => 'nullable|string|max:255',
                'amount_of_losses' => 'required|numeric|min:0',
                'amount_assurance' => 'nullable|numeric|min:0',
                'nom_expert' => 'nullable|string|max:255',
                'status' => 'required|in:open,under_review,waiting_estimate,estimate_approved,under_repair,invoice_received,waiting_payment,closed',
                'matricule_id' => 'required|exists:matricules,id',
                'car_id' => 'required|exists:cars,id',
                'client_id' => 'required|exists:clients,id',
                'reservation_id' => 'nullable|exists:reservations,id',
                'accident_type' => 'required|in:grave,non_grave',
                'procedure_type' => 'required|in:classic,forphie',
                'expert_decision' => 'nullable|in:pending,accepted,rejected',
                'expert_amount' => 'nullable|numeric|min:0',
                'expert_notes' => 'nullable|string',
                'notes' => 'nullable|string',
                'internal_notes' => 'nullable|string',
                'garage_id' => 'nullable|exists:garages,id',
                'inspection_notes' => 'nullable|string',
                'estimated_cost' => 'nullable|numeric|min:0',
                'total_repair_cost' => 'nullable|numeric|min:0',
                'franchise_amount' => 'nullable|numeric|min:0',
                'img_accident' => 'nullable|array',
                'img_evaluation_expert' => 'nullable|array',
                'img_fixed' => 'nullable|array',
                'image_facture' => 'nullable|array',
                'estimate_items' => 'nullable|array',
                'estimate_total_ht' => 'nullable|numeric|min:0',
                'estimate_tva' => 'nullable|numeric|min:0',
                'estimate_total_ttc' => 'nullable|numeric|min:0',
                'estimate_status' => 'nullable|in:draft,sent,approved,rejected',
                'estimate_number' => 'nullable|string|max:255',  // NEW
                'repair_start_date' => 'nullable|date',
                'repair_end_date' => 'nullable|date|after_or_equal:repair_start_date',
                'repair_notes' => 'nullable|string',
                'invoice_number' => 'nullable|string',
                'invoice_items' => 'nullable|array',
                'invoice_total_ht' => 'nullable|numeric|min:0',
                'invoice_tva' => 'nullable|numeric|min:0',
                'invoice_total_ttc' => 'nullable|numeric|min:0',
                'payments' => 'nullable|array',
            ]);

            // Check if matricule is sold
            $matricule = Matricule::find($validated['matricule_id']);
            if ($matricule && $matricule->status === Matricule::STATUS_SOLD) {
                return response()->json([
                    'message' => 'Ce matricule est vendu et ne peut pas être utilisé pour un accident.'
                ], 422);
            }

            // Handle images (base64 → storage)
            $imageFields = ['img_accident', 'img_evaluation_expert', 'img_fixed', 'image_facture'];
            foreach ($imageFields as $field) {
                if (isset($validated[$field]) && is_array($validated[$field])) {
                    $paths = [];
                    foreach ($validated[$field] as $fileData) {
                        if ($this->isBase64File($fileData)) {
                            try {
                                $path = $this->saveBase64File($fileData, 'accidents/' . $field);
                                if ($path) $paths[] = $path;
                            } catch (\Exception $e) {
                                Log::error("Failed to upload file for {$field}: " . $e->getMessage());
                            }
                        } else {
                            $paths[] = $fileData;
                        }
                    }
                    $validated[$field] = !empty($paths) ? $paths : null;
                }
            }

            // Forphie procedure: remove images for fixed and invoice
            if ($validated['procedure_type'] === 'forphie') {
                $validated['img_fixed'] = null;
                $validated['image_facture'] = null;
            }

            $accident = Accident::create($validated);

            // Update matricule status
            if ($accident->matricule_id) {
                $matricule = Matricule::find($accident->matricule_id);
                if ($matricule) {
                    $this->updateMatriculeStatus($matricule, $accident);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Accident created successfully',
                'accident' => $accident->load(['matricule', 'car', 'client', 'garage', 'reservation'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create accident: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create accident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $accident = Accident::with(['matricule', 'car', 'client', 'garage', 'reservation'])->findOrFail($id);

        $accident->img_accident_urls = $accident->img_accident_urls;
        $accident->img_evaluation_expert_urls = $accident->img_evaluation_expert_urls;
        $accident->img_fixed_urls = $accident->img_fixed_urls;
        $accident->image_facture_urls = $accident->image_facture_urls;

        return response()->json(['accident' => $accident]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $accident = Accident::findOrFail($id);
            $oldStatus = $accident->status;
            $oldAccidentType = $accident->accident_type;

            $validated = $request->validate([
                'date_accident' => 'sometimes|date',
                'time_accident' => 'nullable|date_format:H:i',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'police_report_number' => 'nullable|string|max:255',
                'amount_of_losses' => 'sometimes|numeric|min:0',
                'amount_assurance' => 'nullable|numeric|min:0',
                'nom_expert' => 'nullable|string|max:255',
                'status' => 'sometimes|in:open,under_review,waiting_estimate,estimate_approved,under_repair,invoice_received,waiting_payment,closed',
                'matricule_id' => 'sometimes|exists:matricules,id',
                'car_id' => 'sometimes|exists:cars,id',
                'client_id' => 'sometimes|exists:clients,id',
                'reservation_id' => 'nullable|exists:reservations,id',
                'accident_type' => 'sometimes|in:grave,non_grave',
                'procedure_type' => 'sometimes|in:classic,forphie',
                'expert_decision' => 'nullable|in:pending,accepted,rejected',
                'expert_amount' => 'nullable|numeric|min:0',
                'expert_notes' => 'nullable|string',
                'notes' => 'nullable|string',
                'internal_notes' => 'nullable|string',
                'garage_id' => 'nullable|exists:garages,id',
                'inspection_notes' => 'nullable|string',
                'estimated_cost' => 'nullable|numeric|min:0',
                'total_repair_cost' => 'nullable|numeric|min:0',
                'franchise_amount' => 'nullable|numeric|min:0',
                'img_accident' => 'nullable|array',
                'img_evaluation_expert' => 'nullable|array',
                'img_fixed' => 'nullable|array',
                'image_facture' => 'nullable|array',
                'estimate_items' => 'nullable|array',
                'estimate_total_ht' => 'nullable|numeric|min:0',
                'estimate_tva' => 'nullable|numeric|min:0',
                'estimate_total_ttc' => 'nullable|numeric|min:0',
                'estimate_status' => 'nullable|in:draft,sent,approved,rejected',
                'estimate_number' => 'nullable|string|max:255',  // NEW
                'repair_start_date' => 'nullable|date',
                'repair_end_date' => 'nullable|date|after_or_equal:repair_start_date',
                'repair_notes' => 'nullable|string',
                'invoice_number' => 'nullable|string',
                'invoice_items' => 'nullable|array',
                'invoice_total_ht' => 'nullable|numeric|min:0',
                'invoice_tva' => 'nullable|numeric|min:0',
                'invoice_total_ttc' => 'nullable|numeric|min:0',
                'payments' => 'nullable|array',
                'closed_at' => 'nullable|date',
            ]);

            // Image handling
            $imageFields = ['img_accident', 'img_evaluation_expert', 'img_fixed', 'image_facture'];
            foreach ($imageFields as $field) {
                if (isset($validated[$field]) && is_array($validated[$field])) {
                    $paths = [];
                    foreach ($validated[$field] as $fileData) {
                        if ($this->isBase64File($fileData)) {
                            try {
                                $path = $this->saveBase64File($fileData, 'accidents/' . $field);
                                if ($path) $paths[] = $path;
                            } catch (\Exception $e) {
                                Log::error("Failed to upload file for {$field}: " . $e->getMessage());
                            }
                        } else {
                            $paths[] = $fileData;
                        }
                    }
                    $validated[$field] = !empty($paths) ? $paths : null;
                } elseif (array_key_exists($field, $validated) && $validated[$field] === null) {
                    // Delete old files if field set to null
                    if ($accident->$field) {
                        foreach ($accident->$field as $oldFile) {
                            if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                                Storage::disk('public')->delete($oldFile);
                            }
                        }
                    }
                    $validated[$field] = null;
                }
            }

            if (isset($validated['procedure_type']) && $validated['procedure_type'] === 'forphie') {
                $validated['img_fixed'] = null;
                $validated['image_facture'] = null;
            }

            $accident->update($validated);

            // Update matricule status if needed
            if ($accident->matricule_id &&
                (isset($validated['status']) || isset($validated['accident_type']) || isset($validated['matricule_id']))) {
                $matricule = Matricule::find($accident->matricule_id);
                if ($matricule) {
                    $this->updateMatriculeStatus($matricule, $accident, $oldStatus, $oldAccidentType);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Accident updated successfully',
                'accident' => $accident->load(['matricule', 'car', 'client', 'garage', 'reservation'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update accident: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update accident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $accident = Accident::findOrFail($id);

            // Restore matricule status
            if ($accident->matricule_id) {
                $matricule = Matricule::find($accident->matricule_id);
                if ($matricule) {
                    $matricule->update(['status' => 'active']);
                }
            }

            // Delete associated files
            $imageFields = ['img_accident', 'img_evaluation_expert', 'img_fixed', 'image_facture'];
            foreach ($imageFields as $field) {
                if ($accident->$field) {
                    foreach ($accident->$field as $file) {
                        if ($file && Storage::disk('public')->exists($file)) {
                            Storage::disk('public')->delete($file);
                        }
                    }
                }
            }

            $accident->delete();

            DB::commit();

            return response()->json(['message' => 'Accident deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete accident: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete accident: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get next possible statuses
    public function getNextStatuses($id): JsonResponse
    {
        $accident = Accident::findOrFail($id);
        $nextStatuses = $accident->getNextPossibleStatuses();

        return response()->json([
            'current_status' => $accident->status,
            'next_statuses' => $nextStatuses
        ]);
    }

    // Record a payment
    public function recordPayment(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $accident = Accident::findOrFail($id);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payer' => 'required|in:client,insurance',
                'date' => 'required|date',
                'method' => 'required|string|in:cash,check,bank_transfer',
                'reference' => 'nullable|string|max:255',
                'notes' => 'nullable|string'
            ]);

            $payment = [
                'id' => uniqid('pay_'),
                'amount' => (float) $validated['amount'],
                'payer' => $validated['payer'],
                'date' => $validated['date'],
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now()->toDateTimeString()
            ];

            $payments = $accident->payments ?? [];
            $payments[] = $payment;
            $accident->payments = $payments;

            $accident->calculatePaymentTotals();

            if ($accident->remaining_amount <= 0 && $accident->status === 'waiting_payment') {
                $accident->status = 'closed';
                $accident->closed_at = now();
            }

            $accident->save();

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'accident' => $accident->load(['matricule', 'car', 'client'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record payment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    // Close accident
    public function closeAccident($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $accident = Accident::findOrFail($id);

            if ($accident->status === 'closed') {
                return response()->json(['message' => 'Accident is already closed'], 422);
            }

            if ($accident->remaining_amount > 0) {
                return response()->json([
                    'message' => 'Cannot close accident with remaining balance: ' . $accident->remaining_amount . ' DH'
                ], 422);
            }

            if (!$accident->total_repair_cost || $accident->total_repair_cost <= 0) {
                return response()->json([
                    'message' => 'Cannot close accident without total repair cost'
                ], 422);
            }

            $accident->status = 'closed';
            $accident->closed_at = now();
            $accident->save();

            if ($accident->matricule_id) {
                $matricule = Matricule::find($accident->matricule_id);
                if ($matricule && $matricule->status === 'inactive') {
                    $matricule->update(['status' => 'active']);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Accident closed successfully',
                'accident' => $accident->load(['matricule', 'car', 'client'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close accident: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to close accident: ' . $e->getMessage()
            ], 500);
        }
    }

    // Download PDFs (unchanged)
    public function downloadReport($id)
    {
        $accident = Accident::with(['matricule.car', 'client', 'garage', 'reservation'])->findOrFail($id);
        $data = [
            'accident' => $accident,
            'title' => 'Rapport d\'Accident',
            'company' => $this->getCompanyInfo()
        ];
        $pdf = Pdf::loadView('pdf.accident-report', $data);
        return $pdf->download('Accident_' . $accident->accident_number . '.pdf');
    }

    public function downloadEstimate($id)
    {
        $accident = Accident::with(['matricule.car', 'client', 'garage'])->findOrFail($id);
        $data = [
            'accident' => $accident,
            'title' => 'Devis de Réparation',
            'company' => $this->getCompanyInfo()
        ];
        $pdf = Pdf::loadView('pdf.accident-estimate', $data);
        return $pdf->download('Devis_' . $accident->estimate_number ?? $accident->accident_number . '.pdf');
    }

    public function downloadInvoice($id)
    {
        $accident = Accident::with(['matricule.car', 'client', 'garage'])->findOrFail($id);
        $data = [
            'accident' => $accident,
            'title' => 'Facture de Réparation',
            'company' => $this->getCompanyInfo()
        ];
        $pdf = Pdf::loadView('pdf.accident-invoice', $data);
        return $pdf->download('Facture_' . $accident->invoice_number ?? $accident->accident_number . '.pdf');
    }

    private function getCompanyInfo()
    {
        return [
            'name' => 'JINA BUSINESS',
            'address' => 'LOT ERRACHAD N° 706 TR 02 MAG 01 MEDIOUNA, CASABLANCA - Maroc',
            'rc' => '624023',
            'if' => '65912528',
            'tp' => '37701443',
            'cnss' => '5317601',
            'ice' => '003478448000020',
            'tel' => '06 63 04 95 75 - 06 67 47 47 45',
            'email' => 'jinabusiness0@gmail.com'
        ];
    }

    // File helpers
    private function isBase64File($string)
    {
        if (!is_string($string) || empty($string)) {
            return false;
        }
        if (strpos($string, '/') !== false || strpos($string, '\\') !== false) {
            return false;
        }
        if (strpos($string, 'data:') === 0) {
            $parts = explode(',', $string);
            if (count($parts) === 2) {
                $fileData = base64_decode($parts[1], true);
                return $fileData !== false;
            }
        }
        if (base64_decode($string, true) !== false) {
            return true;
        }
        return false;
    }

    private function saveBase64File($base64File, $directory)
    {
        try {
            $fileData = null;
            $extension = 'png';

            if (strpos($base64File, 'data:') === 0) {
                $parts = explode(',', $base64File);
                if (count($parts) !== 2) {
                    throw new \Exception('Invalid data URL format');
                }
                $mimeParts = explode(';', $parts[0]);
                $mimeType = str_replace('data:', '', $mimeParts[0]);
                $fileData = base64_decode($parts[1]);
                $extension = $this->getExtensionFromMime($mimeType);
            } else {
                $fileData = base64_decode($base64File);
            }

            if ($fileData === false) {
                throw new \Exception('Failed to decode base64 file');
            }

            $filename = uniqid() . '_' . time() . '.' . $extension;
            $path = $directory . '/' . $filename;

            Storage::disk('public')->makeDirectory($directory);
            Storage::disk('public')->put($path, $fileData);

            return $path;
        } catch (\Exception $e) {
            Log::error('Error saving base64 file: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getExtensionFromMime($mimeType)
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        return $mimeToExt[$mimeType] ?? 'png';
    }

    // Update matricule status based on accident
    private function updateMatriculeStatus($matricule, $accident, $oldStatus = null, $oldAccidentType = null)
    {
        $shouldBeInactive = false;

        if ($accident->accident_type === 'grave') {
            $shouldBeInactive = !in_array($accident->status, ['waiting_payment', 'closed']);
        } else {
            $shouldBeInactive = $accident->status === 'under_repair';
        }

        $newStatus = $shouldBeInactive ? 'inactive' : 'active';

        if ($matricule->status !== $newStatus) {
            $matricule->update(['status' => $newStatus]);
        }
    }

    // Statistics
    public function statistics(): JsonResponse
    {
        $totalAccidents = Accident::count();
        $openAccidents = Accident::where('status', '!=', 'closed')->count();
        $closedAccidents = Accident::where('status', 'closed')->count();
        $underRepair = Accident::where('status', 'under_repair')->count();
        $totalLosses = Accident::sum('amount_of_losses');
        $totalRepairCost = Accident::sum('total_repair_cost');
        $totalPaid = Accident::sum('total_paid');

        return response()->json([
            'total_accidents' => $totalAccidents,
            'open_accidents' => $openAccidents,
            'closed_accidents' => $closedAccidents,
            'under_repair' => $underRepair,
            'total_losses' => $totalLosses,
            'total_repair_cost' => $totalRepairCost,
            'total_paid' => $totalPaid
        ]);
    }
}