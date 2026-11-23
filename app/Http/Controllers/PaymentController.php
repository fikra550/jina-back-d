<?php

namespace App\Http\Controllers;

use App\Models\CarFinancing;
use App\Models\CarFinancingPayment;
use App\Models\Matricule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\PagePermissionHelper;

class PaymentController extends Controller
{
    use PagePermissionHelper;

    /**
     * Display a listing of car financings.
     */
    public function index(): JsonResponse
    {
        $this->authorizePageAccess('payments');

        $financings = CarFinancing::with(['matricule.car', 'payments'])->get();

        $stats = [
            'total' => $financings->count(),
            'active' => $financings->where('status', 'active')->count(),
            'completed' => $financings->where('status', 'completed')->count(),
            'totalRemaining' => $financings->sum('total_remaining')
        ];

        return response()->json([
            'financings' => $financings,
            'stats' => $stats
        ]);
    }

    /**
     * Store a new car financing.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizePageAccess('payments');

        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'matricule_id' => 'required|exists:matricules,id',
                'dossier_number' => 'required|string|unique:car_financings',
                'account_number' => 'nullable|string',
                'credit_type' => 'required|in:vehicule_entreprise,vehicule_personnel,other',
                'contract_date' => 'required|date',
                'credit_amount' => 'required|numeric|min:0',
                'preti_interet_ttc_differe' => 'nullable|numeric|min:0',
                'duration_months' => 'required|integer|min:1',
                'differed_months' => 'integer|min:0',
                'periodicity' => 'string|in:mensuel,trimestriel,semestriel,annuel',
                'total_installments' => 'required|integer|min:1',
                'first_installment_date' => 'required|date',
                'last_installment_date' => 'required|date|after:first_installment_date',
                'bank_name' => 'nullable|string',
                'bank_account' => 'nullable|string',
                'installment_amount' => 'required|numeric|min:0',
                'prestation_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
                'status' => 'in:active,completed,late,defaulted'
            ]);

            // Prevent sold matricule
            $matricule = Matricule::find($validated['matricule_id']);
            if ($matricule && $matricule->status === Matricule::STATUS_SOLD) {
                return response()->json([
                    'message' => 'Ce matricule est vendu et ne peut pas être utilisé pour un financement.'
                ], 422);
            }

            $validated['total_remaining'] = $validated['credit_amount'];
            $validated['total_paid'] = 0;
            $validated['current_installment_number'] = 0;
            $validated['status'] = $validated['status'] ?? 'active';
            $validated['periodicity'] = $validated['periodicity'] ?? 'mensuel';

            $financing = CarFinancing::create($validated);

            DB::commit();

            return response()->json([
                'message' => 'Financing created successfully',
                'financing' => $financing->load('matricule.car')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create financing: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create financing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified financing.
     */
    public function show($id): JsonResponse
    {
        $this->authorizePageAccess('payments');
        $financing = CarFinancing::with(['matricule.car', 'payments'])->findOrFail($id);
        return response()->json(['financing' => $financing]);
    }

    /**
     * Update the specified financing.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('payments');

        try {
            DB::beginTransaction();

            $financing = CarFinancing::findOrFail($id);

            $validated = $request->validate([
                'matricule_id' => 'sometimes|exists:matricules,id',
                'dossier_number' => 'sometimes|string|unique:car_financings,dossier_number,' . $id,
                'account_number' => 'nullable|string',
                'credit_type' => 'sometimes|in:vehicule_entreprise,vehicule_personnel,other',
                'contract_date' => 'sometimes|date',
                'credit_amount' => 'sometimes|numeric|min:0',
                'preti_interet_ttc_differe' => 'nullable|numeric|min:0',
                'duration_months' => 'sometimes|integer|min:1',
                'differed_months' => 'sometimes|integer|min:0',
                'periodicity' => 'sometimes|string|in:mensuel,trimestriel,semestriel,annuel',
                'total_installments' => 'sometimes|integer|min:1',
                'first_installment_date' => 'sometimes|date',
                'last_installment_date' => 'sometimes|date',
                'bank_name' => 'nullable|string',
                'bank_account' => 'nullable|string',
                'installment_amount' => 'sometimes|numeric|min:0',
                'prestation_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
                'status' => 'sometimes|in:active,completed,late,defaulted'
            ]);

            if (isset($validated['credit_amount'])) {
                $validated['total_remaining'] = $validated['credit_amount'] - $financing->total_paid;
            }

            $financing->update($validated);

            DB::commit();

            return response()->json([
                'message' => 'Financing updated successfully',
                'financing' => $financing->load('matricule.car')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update financing: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update financing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified financing.
     */
    public function destroy($id): JsonResponse
    {
        $this->authorizePageAccess('payments');

        try {
            DB::beginTransaction();
            $financing = CarFinancing::findOrFail($id);
            $financing->payments()->delete();
            $financing->delete();
            DB::commit();
            return response()->json(['message' => 'Financing deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete financing: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete financing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments for a specific financing.
     */
    public function getPayments($financingId): JsonResponse
    {
        $this->authorizePageAccess('payments');
        $payments = CarFinancingPayment::where('car_financing_id', $financingId)
            ->orderBy('installment_number')
            ->get();
        return response()->json(['payments' => $payments]);
    }

    /**
     * Record a payment for an installment.
     */
    public function recordPayment(Request $request, $financingId): JsonResponse
    {
        $this->authorizePageAccess('payments');

        try {
            DB::beginTransaction();

            $financing = CarFinancing::findOrFail($financingId);

            $validated = $request->validate([
                'installment_number' => 'required|integer|min:1',
                'paid_amount' => 'required|numeric|min:0',
                'paid_date' => 'required|date',
                'payment_method' => 'nullable|string',
                'transaction_reference' => 'nullable|string',
                'payment_notes' => 'nullable|string'
            ]);

            $payment = CarFinancingPayment::where('car_financing_id', $financingId)
                ->where('installment_number', $validated['installment_number'])
                ->first();

            if (!$payment) {
                return response()->json(['message' => 'Installment not found'], 404);
            }

            if ($payment->status === 'paid') {
                return response()->json(['message' => 'This installment has already been paid'], 422);
            }

            $payment->update([
                'status' => 'paid',
                'paid_amount' => $validated['paid_amount'],
                'paid_date' => $validated['paid_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_notes' => $validated['payment_notes'] ?? null
            ]);

            $totalPaid = CarFinancingPayment::where('car_financing_id', $financingId)
                ->where('status', 'paid')
                ->sum('paid_amount');

            $paidInstallments = CarFinancingPayment::where('car_financing_id', $financingId)
                ->where('status', 'paid')
                ->count();

            $financing->update([
                'total_paid' => $totalPaid,
                'total_remaining' => $financing->credit_amount - $totalPaid,
                'current_installment_number' => $paidInstallments,
                'status' => $paidInstallments >= $financing->total_installments ? 'completed' : 'active'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'payment' => $payment,
                'financing' => $financing->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record payment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate amortization schedule for a financing.
     */
    public function generateSchedule(Request $request, $financingId): JsonResponse
    {
        $this->authorizePageAccess('payments');

        try {
            $financing = CarFinancing::findOrFail($financingId);

            $validated = $request->validate([
                'interest_rate' => 'required|numeric|min:0|max:100',
                'tva_rate' => 'nullable|numeric|min:0|max:100'
            ]);

            $interestRate = $validated['interest_rate'] / 100;
            $tvaRate = ($validated['tva_rate'] ?? 20) / 100;
            $principal = $financing->credit_amount;
            $months = $financing->duration_months;
            $installmentAmount = $financing->installment_amount;

            $monthlyRate = $interestRate / 12;

            $schedule = [];
            $remainingPrincipal = $principal;
            $totalInterest = 0;

            for ($i = 1; $i <= $months; $i++) {
                $interest = $remainingPrincipal * $monthlyRate;
                $capital = $installmentAmount - $interest;
                $tva = $interest * $tvaRate;

                if ($i == $months) {
                    $capital = $remainingPrincipal;
                    $interest = $installmentAmount - $capital;
                    $tva = $interest * $tvaRate;
                }

                $remainingPrincipal -= $capital;
                $totalInterest += $interest;

                $schedule[] = [
                    'installment_number' => $i,
                    'due_date' => now()->addMonths($i)->format('Y-m-d'),
                    'capital_amount' => round($capital, 2),
                    'interest_amount' => round($interest, 2),
                    'tva_amount' => round($tva, 2),
                    'total_amount' => round($installmentAmount, 2),
                    'remaining_capital' => max(0, round($remainingPrincipal, 2)),
                    'remaining_interest' => round($totalInterest, 2),
                    'remaining_total' => max(0, round($remainingPrincipal + $totalInterest, 2))
                ];
            }

            $financing->update([
                'amortization_schedule' => $schedule
            ]);

            foreach ($schedule as $item) {
                CarFinancingPayment::updateOrCreate(
                    [
                        'car_financing_id' => $financingId,
                        'installment_number' => $item['installment_number']
                    ],
                    [
                        'due_date' => $item['due_date'],
                        'capital_amount' => $item['capital_amount'],
                        'interest_amount' => $item['interest_amount'],
                        'tva_amount' => $item['tva_amount'],
                        'total_amount' => $item['total_amount'],
                        'remaining_capital' => $item['remaining_capital'],
                        'remaining_interest' => $item['remaining_interest'],
                        'remaining_total' => $item['remaining_total'],
                        'status' => 'pending'
                    ]
                );
            }

            return response()->json([
                'message' => 'Amortization schedule generated successfully',
                'schedule' => $schedule,
                'payments_created' => count($schedule)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate schedule: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for all financings.
     */
    public function getStats(): JsonResponse
    {
        $this->authorizePageAccess('payments');

        $stats = [
            'total' => CarFinancing::count(),
            'active' => CarFinancing::where('status', 'active')->count(),
            'completed' => CarFinancing::where('status', 'completed')->count(),
            'late' => CarFinancing::where('status', 'late')->count(),
            'defaulted' => CarFinancing::where('status', 'defaulted')->count(),
            'totalCreditAmount' => CarFinancing::sum('credit_amount'),
            'totalPaid' => CarFinancing::sum('total_paid'),
            'totalRemaining' => CarFinancing::sum('total_remaining'),
            'monthlyRevenue' => CarFinancing::where('status', 'active')->sum('installment_amount')
        ];

        return response()->json($stats);
    }
}