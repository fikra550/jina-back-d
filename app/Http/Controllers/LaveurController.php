<?php

namespace App\Http\Controllers;

use App\Models\Lavage;
use App\Models\Laveur;
use App\Models\LaveurPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\PagePermissionHelper;

class LaveurController extends Controller
{
    use PagePermissionHelper;

    /* =====================================================
     |  LIST  (avec gagné / payé / reste pour chaque laveur)
     |  ?from=YYYY-MM-DD&to=YYYY-MM-DD  -> filtre la période
     ===================================================== */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $from = $request->query('from');
        $to   = $request->query('to');

        $earned = Lavage::where('status', 'completed')
            ->whereNotNull('laveur_id')
            ->when($from, fn($q) => $q->whereDate('date_lavage', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('date_lavage', '<=', $to))
            ->selectRaw('laveur_id, COUNT(*) as lavages_count, COALESCE(SUM(laveur_amount), 0) as earned')
            ->groupBy('laveur_id')
            ->get()
            ->keyBy('laveur_id');

        $paid = LaveurPayment::query()
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->selectRaw('laveur_id, COALESCE(SUM(amount), 0) as paid')
            ->groupBy('laveur_id')
            ->get()
            ->keyBy('laveur_id');

        $laveurs = Laveur::orderByDesc('is_active')->orderBy('name')->get()->map(function ($l) use ($earned, $paid) {
            $e = (float) ($earned[$l->id]->earned ?? 0);
            $p = (float) ($paid[$l->id]->paid ?? 0);

            $l->lavages_count = (int) ($earned[$l->id]->lavages_count ?? 0);
            $l->earned        = round($e, 2);
            $l->paid          = round($p, 2);
            $l->balance       = round($e - $p, 2);
            return $l;
        });

        $totals = [
            'earned'  => round($laveurs->sum('earned'), 2),
            'paid'    => round($laveurs->sum('paid'), 2),
            'balance' => round($laveurs->sum('balance'), 2),
            'lavages' => $laveurs->sum('lavages_count'),
        ];

        return response()->json(['laveurs' => $laveurs, 'totals' => $totals]);
    }

    /* =====================================================
     |  SHOW  (détail du calcul : lavages + versements)
     ===================================================== */
    public function show(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $laveur = Laveur::findOrFail($id);
        $from   = $request->query('from');
        $to     = $request->query('to');

        $lavages = Lavage::with('client')
            ->where('laveur_id', $laveur->id)
            ->where('status', 'completed')
            ->when($from, fn($q) => $q->whereDate('date_lavage', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('date_lavage', '<=', $to))
            ->orderByDesc('date_lavage')
            ->orderByDesc('id')
            ->get()
            ->map(fn($l) => [
                'id'            => $l->id,
                'date_lavage'   => $l->date_lavage,
                'client_name'   => trim($l->client_name ?: trim(($l->client->prenom ?? '') . ' ' . ($l->client->nom ?? ''))),
                'vehicle_plate' => $l->vehicle_plate,
                'type'          => $l->type,
                'price'         => (float) $l->price,
                'laveur_amount' => (float) $l->laveur_amount,
            ]);

        $payments = LaveurPayment::where('laveur_id', $laveur->id)
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $earned = round($lavages->sum('laveur_amount'), 2);
        $paid   = round($payments->sum('amount'), 2);

        return response()->json([
            'laveur'   => $laveur,
            'summary'  => [
                'lavages_count' => $lavages->count(),
                'earned'        => $earned,
                'paid'          => $paid,
                'balance'       => round($earned - $paid, 2),
            ],
            'lavages'  => $lavages,
            'payments' => $payments,
        ]);
    }

    /* =====================================================
     |  CREATE / UPDATE / DELETE
     ===================================================== */
    public function store(Request $request): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $validated = $this->validateLaveur($request);
        $laveur = Laveur::create($validated);

        return response()->json(['laveur' => $laveur], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $laveur    = Laveur::findOrFail($id);
        $validated = $this->validateLaveur($request, true);
        $laveur->update($validated);

        return response()->json(['laveur' => $laveur->fresh()]);
    }

    public function destroy($id): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $laveur = Laveur::findOrFail($id);

        // On garde l'historique de paie intact : pas de suppression si le
        // laveur a déjà des lavages ou des versements -> on le désactive.
        if ($laveur->lavages()->exists() || $laveur->payments()->exists()) {
            return response()->json([
                'message' => 'Ce laveur a des lavages ou des paiements enregistrés. Désactivez-le plutôt que de le supprimer.',
            ], 422);
        }

        $laveur->delete();

        return response()->json(['message' => 'Laveur supprimé']);
    }

    /* =====================================================
     |  PAYMENTS (versements au laveur)
     ===================================================== */
    public function addPayment(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $laveur = Laveur::findOrFail($id);

        $validated = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method'       => 'required|in:cash,check,card,transfer',
            'notes'        => 'nullable|string',
        ]);

        $validated['laveur_id']  = $laveur->id;
        $validated['created_by'] = $this->currentUserName();

        $payment = LaveurPayment::create($validated);

        return response()->json(['message' => 'Paiement enregistré', 'payment' => $payment], 201);
    }

    public function removePayment($id, $paymentId): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $payment = LaveurPayment::where('laveur_id', $id)->findOrFail($paymentId);
        $payment->delete();

        return response()->json(['message' => 'Paiement supprimé']);
    }

    /* =====================================================
     |  HELPERS
     ===================================================== */
    private function validateLaveur(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes|' : '';

        $validated = $request->validate([
            'name'      => $req . 'required|string|max:255',
            'phone'     => 'nullable|string|max:30',
            'pay_type'  => $req . 'required|in:percentage,fixed',
            'pay_rate'  => $req . 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'notes'     => 'nullable|string',
        ]);

        $type = $validated['pay_type'] ?? $request->input('pay_type');
        if ($type === 'percentage' && isset($validated['pay_rate']) && $validated['pay_rate'] > 100) {
            abort(response()->json([
                'message' => 'Le pourcentage ne peut pas dépasser 100.',
                'errors'  => ['pay_rate' => ['Le pourcentage ne peut pas dépasser 100.']],
            ], 422));
        }

        return $validated;
    }

    private function currentUserName(): string
    {
        $u = auth()->user();
        return $u ? ($u->Fullname ?? $u->name ?? 'System') : 'System';
    }
}