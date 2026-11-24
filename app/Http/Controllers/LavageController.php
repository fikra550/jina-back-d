<?php

namespace App\Http\Controllers;

use App\Models\Lavage;
use App\Models\Laveur;
use App\Models\LavageProduct;
use App\Models\Matricule;
use App\Models\Product;
use App\Models\ProductStockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\PagePermissionHelper;

class LavageController extends Controller
{
    use PagePermissionHelper;

    /** Relations renvoyées après chaque action (le front remplace la ligne entière). */
    private const FULL = [
        'matricule.car', 'car', 'client', 'reservation',
        'assignedUser', 'laveur', 'lavageProducts.product',
    ];

    /* =====================================================
     |  LIST
     ===================================================== */
    public function index(): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $lavages = Lavage::with([
            'matricule.car', 'car', 'client', 'reservation',
            'assignedUser', 'laveur', 'lavageProducts.product',
        ])
            ->orderBy('date_lavage', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->each(function ($l) {
                $l->photos_before_urls = $l->photos_before_urls;
                $l->photos_after_urls  = $l->photos_after_urls;
            });

        $stats = [
            'total'         => $lavages->count(),
            'pending'       => $lavages->where('status', 'pending')->count(),
            'in_progress'   => $lavages->where('status', 'in_progress')->count(),
            'completed'     => $lavages->where('status', 'completed')->count(),
            'cancelled'     => $lavages->where('status', 'cancelled')->count(),
            'total_revenue' => $lavages->where('status', 'completed')->sum('price'),
            'total_paid'    => $lavages->where('status', 'completed')->sum('amount_paid'),
            'unpaid_amount' => $lavages->where('status', 'completed')->sum(fn($l) => $l->remaining_amount),
            'avg_duration'  => $lavages->where('status', 'completed')->whereNotNull('duration_minutes')->avg('duration_minutes'),
            'avg_rating'    => $lavages->whereNotNull('rating')->avg('rating'),
        ];

        return response()->json(['lavages' => $lavages, 'stats' => $stats]);
    }

    /* =====================================================
     |  CREATE
     ===================================================== */
    public function store(Request $request): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $validated = $request->validate([
            'matricule_id'             => 'nullable|exists:matricules,id',
            'car_id'                   => 'nullable|exists:cars,id',
            'client_id'                => 'nullable|exists:clients,id',
            'client_name'              => 'nullable|string|max:255',
            'reservation_id'           => 'nullable|exists:reservations,id',
            'date_lavage'              => 'required|date',
            'time_lavage'              => 'nullable|date_format:H:i',
            'type'                     => 'required|in:externe,interne,complet',
            'services'                 => 'nullable|array',
            'services.*.id'            => 'nullable',
            'services.*.name'          => 'required_with:services|string|max:255',
            'services.*.price'         => 'required_with:services|numeric|min:0',
            'services.*.quantity'      => 'nullable|integer|min:1',
            'price'                    => 'required|numeric|min:0',
            'subtotal'                 => 'nullable|numeric|min:0',
            'discount_amount'          => 'nullable|numeric|min:0',
            'tva_amount'               => 'nullable|numeric|min:0',
            'status'                   => 'sometimes|in:pending,in_progress,completed,cancelled',
            'payment_status'           => 'sometimes|in:paid,unpaid,partial',
            'amount_paid'              => 'sometimes|numeric|min:0',
            'washer_name'              => 'nullable|string|max:255',
            'laveur_id'                => 'nullable|exists:laveurs,id',
            'vehicle_plate'            => 'nullable|string|max:50',
            'assigned_to'              => 'nullable|exists:utilisateurs,id',
            'bay'                      => 'nullable|string|max:50',
            'vehicle_condition_before' => 'nullable|string',
            'notes'                    => 'nullable|string',
            'photos_before'            => 'nullable|array',
            'photos_after'             => 'nullable|array',
            'products'                 => 'nullable|array',
            'products.*.product_id'    => 'required_with:products|exists:products,id',
            'products.*.quantity'      => 'required_with:products|integer|min:1',
            'products.*.unit_cost'     => 'nullable|numeric|min:0',
        ]);

        $validated['created_by'] = $this->currentUserName();

        // Laveur: copy the name + compute what the laveur earns on this lavage
        $this->applyLaveur($validated);

        // Auto-fill car_id from matricule
        if (!empty($validated['matricule_id']) && empty($validated['car_id'])) {
            $m = Matricule::find($validated['matricule_id']);
            if ($m) $validated['car_id'] = $m->car_id;
        }

        // Photos (base64 → storage)
        foreach (['photos_before', 'photos_after'] as $field) {
            if (!empty($validated[$field]) && is_array($validated[$field])) {
                $paths = [];
                foreach ($validated[$field] as $file) {
                    if (is_string($file) && str_starts_with($file, 'data:')) {
                        $saved = $this->saveBase64Image($file, 'lavages/' . $field);
                        if ($saved) $paths[] = $saved;
                    } elseif (is_string($file)) {
                        $paths[] = $file;
                    }
                }
                $validated[$field] = !empty($paths) ? $paths : null;
            }
        }

        // Subtotal from services
        if (empty($validated['subtotal']) && !empty($validated['services'])) {
            $sum = 0;
            foreach ($validated['services'] as $s) {
                $sum += ((float) ($s['price'] ?? 0)) * ((int) ($s['quantity'] ?? 1));
            }
            $validated['subtotal'] = $sum;
        }

        // Initial payment history
        if (!empty($validated['amount_paid']) && $validated['amount_paid'] > 0) {
            $validated['payment_history'] = [[
                'id'         => uniqid('lav_pay_'),
                'amount'     => (float) $validated['amount_paid'],
                'date'       => now()->toDateString(),
                'method'     => 'cash',
                'notes'      => 'Paiement initial',
                'created_at' => now()->toDateTimeString(),
            ]];
        } else {
            $validated['payment_history'] = [];
        }

        // Auto payment_status
        $paid = array_sum(array_map(fn($p) => (float) $p['amount'], $validated['payment_history']));
        $validated['amount_paid'] = $paid;
        if (!isset($validated['payment_status'])) {
            $validated['payment_status'] = $paid <= 0
                ? 'unpaid'
                : ($paid >= (float) $validated['price'] ? 'paid' : 'partial');
        }

        // Workflow timestamps
        if (($validated['status'] ?? null) === 'in_progress') {
            $validated['started_at'] = now();
        }
        if (($validated['status'] ?? null) === 'completed') {
            $validated['started_at']   = now();
            $validated['completed_at'] = now();
        }

        $productsData = $validated['products'] ?? [];
        unset($validated['products']);

        try {
            DB::beginTransaction();

            $lavage = Lavage::create($validated);

            if (!empty($productsData)) {
                $this->applyProducts($lavage, $this->mergeProductRows($productsData), $validated['created_by']);
            }

            DB::commit();

            return response()->json([
                'lavage' => $lavage->load(self::FULL),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lavage create failed: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /* =====================================================
     |  SHOW
     ===================================================== */
    public function show($id): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $lavage = Lavage::with([
            'matricule.car', 'car', 'client', 'reservation',
            'assignedUser', 'laveur', 'lavageProducts.product',
        ])->findOrFail($id);

        $lavage->photos_before_urls = $lavage->photos_before_urls;
        $lavage->photos_after_urls  = $lavage->photos_after_urls;

        return response()->json(['lavage' => $lavage]);
    }

    /* =====================================================
     |  UPDATE
     ===================================================== */
    public function update(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $lavage    = Lavage::findOrFail($id);
        $oldStatus = $lavage->status;

        $validated = $request->validate([
            'matricule_id'             => 'nullable|exists:matricules,id',
            'car_id'                   => 'nullable|exists:cars,id',
            'client_id'                => 'nullable|exists:clients,id',
            'client_name'              => 'nullable|string|max:255',
            'reservation_id'           => 'nullable|exists:reservations,id',
            'date_lavage'              => 'sometimes|date',
            'time_lavage'              => 'nullable|date_format:H:i',
            'type'                     => 'sometimes|in:externe,interne,complet',
            'services'                 => 'nullable|array',
            'price'                    => 'sometimes|numeric|min:0',
            'subtotal'                 => 'nullable|numeric|min:0',
            'discount_amount'          => 'nullable|numeric|min:0',
            'tva_amount'               => 'nullable|numeric|min:0',
            'status'                   => 'sometimes|in:pending,in_progress,completed,cancelled',
            'payment_status'           => 'sometimes|in:paid,unpaid,partial',
            'amount_paid'              => 'sometimes|numeric|min:0',
            'washer_name'              => 'nullable|string|max:255',
            'laveur_id'                => 'nullable|exists:laveurs,id',
            'vehicle_plate'            => 'nullable|string|max:50',
            'assigned_to'              => 'nullable|exists:utilisateurs,id',
            'bay'                      => 'nullable|string|max:50',
            'vehicle_condition_before' => 'nullable|string',
            'vehicle_condition_after'  => 'nullable|string',
            'rating'                   => 'nullable|integer|min:1|max:5',
            'feedback'                 => 'nullable|string',
            'notes'                    => 'nullable|string',
            'photos_before'            => 'nullable|array',
            'photos_after'             => 'nullable|array',
            'products'                 => 'nullable|array',
            'products.*.product_id'    => 'required_with:products|exists:products,id',
            'products.*.quantity'      => 'required_with:products|integer|min:1',
            'products.*.unit_cost'     => 'nullable|numeric|min:0',
        ]);

        // Photos (replace + delete old files)
        foreach (['photos_before', 'photos_after'] as $field) {
            if (array_key_exists($field, $validated) && is_array($validated[$field])) {
                $paths = [];
                foreach ($validated[$field] as $file) {
                    if (is_string($file) && str_starts_with($file, 'data:')) {
                        $saved = $this->saveBase64Image($file, 'lavages/' . $field);
                        if ($saved) $paths[] = $saved;
                    } elseif (is_string($file)) {
                        $paths[] = $file;
                    }
                }

                foreach (($lavage->$field ?? []) as $old) {
                    if (!in_array($old, $paths, true) && Storage::disk('public')->exists($old)) {
                        Storage::disk('public')->delete($old);
                    }
                }

                $validated[$field] = !empty($paths) ? $paths : null;
            }
        }

        // Status transitions
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            if ($validated['status'] === 'in_progress' && !$lavage->started_at) {
                $validated['started_at'] = now();
            }
            if ($validated['status'] === 'completed') {
                if (!$lavage->started_at) $validated['started_at'] = now();
                if (!$lavage->completed_at) {
                    $validated['completed_at'] = now();
                    $started = $validated['started_at'] ?? $lavage->started_at;
                    $validated['duration_minutes'] = (int) $started->diffInMinutes($validated['completed_at']);
                }
            }
        }

        // Subtotal from services
        if (array_key_exists('services', $validated) && empty($validated['subtotal'])) {
            $sum = 0;
            foreach ($validated['services'] ?? [] as $s) {
                $sum += ((float) ($s['price'] ?? 0)) * ((int) ($s['quantity'] ?? 1));
            }
            $validated['subtotal'] = $sum;
        }

        // Recompute payment totals
        $paid = array_sum(array_map(fn($p) => (float) $p['amount'], $lavage->payment_history ?? []));
        if (isset($validated['amount_paid'])) $paid = (float) $validated['amount_paid'];
        $finalPrice = (float) ($validated['price'] ?? $lavage->price);
        $validated['amount_paid'] = $paid;
        if (!isset($validated['payment_status'])) {
            $validated['payment_status'] = $paid <= 0
                ? 'unpaid'
                : ($paid >= $finalPrice ? 'paid' : 'partial');
        }

        // Laveur: copy the name + (re)compute earnings when laveur or price changed
        $this->applyLaveur($validated, $lavage);

        $productsData = $validated['products'] ?? null;
        unset($validated['products']);

        try {
            DB::beginTransaction();

            $lavage->update($validated);

            // Only resync products if the client sent the products array.
            // Skip stock restore if the lavage was already cancelled previously,
            // otherwise we'd double count stock.
            if ($productsData !== null) {
                if ($oldStatus !== 'cancelled') {
                    $this->restoreProductStock($lavage);
                }
                LavageProduct::where('lavage_id', $lavage->id)->delete();

                if (!empty($productsData)) {
                    $this->applyProducts(
                        $lavage,
                        $this->mergeProductRows($productsData),
                        $lavage->created_by ?? $this->currentUserName()
                    );
                }
            }

            DB::commit();

            return response()->json([
                'lavage' => $lavage->fresh()->load(self::FULL),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lavage update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /* =====================================================
     |  DELETE
     ===================================================== */
    public function destroy($id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        try {
            DB::beginTransaction();

            // Do not restore stock if the lavage was already cancelled
            // (stock was already given back in cancel()).
            if ($lavage->status !== 'cancelled') {
                $this->restoreProductStock($lavage);
            }

            foreach (['photos_before', 'photos_after'] as $field) {
                foreach (($lavage->$field ?? []) as $path) {
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            $lavage->delete();
            DB::commit();

            return response()->json(['message' => 'Lavage supprimé']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /* =====================================================
     |  WORKFLOW
     ===================================================== */
    public function start(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        if ($lavage->status !== 'pending') {
            return response()->json(['message' => 'Ce lavage ne peut pas être démarré'], 422);
        }

        $lavage->startNow();
        $lavage->save();

        return response()->json(['message' => 'Lavage démarré', 'lavage' => $lavage->fresh()->load(self::FULL)]);
    }

    public function complete(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        if (!in_array($lavage->status, ['pending', 'in_progress'])) {
            return response()->json(['message' => 'Ce lavage ne peut pas être terminé'], 422);
        }

        $validated = $request->validate([
            'vehicle_condition_after' => 'nullable|string',
            'photos_after'            => 'nullable|array',
            'amount_paid'             => 'sometimes|numeric|min:0',
            'payment_method'          => 'nullable|in:cash,check,card,transfer',
            'notes'                   => 'nullable|string',
        ]);

        if (!empty($validated['photos_after']) && is_array($validated['photos_after'])) {
            $paths = [];
            foreach ($validated['photos_after'] as $file) {
                if (is_string($file) && str_starts_with($file, 'data:')) {
                    $saved = $this->saveBase64Image($file, 'lavages/photos_after');
                    if ($saved) $paths[] = $saved;
                } elseif (is_string($file)) {
                    $paths[] = $file;
                }
            }
            if (!empty($paths)) $validated['photos_after'] = $paths;
        }

        if (!empty($validated['amount_paid']) && $validated['amount_paid'] > 0) {
            $lavage->addPayment(
                (float) $validated['amount_paid'],
                now()->toDateString(),
                $validated['payment_method'] ?? 'cash',
                $validated['notes'] ?? 'Paiement de clôture'
            );
        }

        if (isset($validated['vehicle_condition_after'])) $lavage->vehicle_condition_after = $validated['vehicle_condition_after'];
        if (isset($validated['photos_after']))            $lavage->photos_after            = $validated['photos_after'];

        if ($lavage->laveur_id && $lavage->laveur_amount === null) {
            $lavage->laveur_amount = $this->laveurAmount((int) $lavage->laveur_id, (float) $lavage->price);
        }

        $lavage->completeNow();
        $lavage->save();

        return response()->json([
            'message' => 'Lavage terminé',
            'lavage'  => $lavage->fresh()->load(self::FULL),
        ]);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        if ($lavage->status === 'completed') {
            return response()->json(['message' => 'Impossible d\'annuler un lavage terminé'], 422);
        }

        // Guard against double-cancel: if already cancelled, don't restore twice
        if ($lavage->status === 'cancelled') {
            return response()->json([
                'message' => 'Lavage déjà annulé',
                'lavage'  => $lavage->fresh()->load(self::FULL),
            ]);
        }

        try {
            DB::beginTransaction();
            $this->restoreProductStock($lavage);
            $lavage->update(['status' => 'cancelled']);
            DB::commit();

            return response()->json([
                'message' => 'Lavage annulé',
                'lavage'  => $lavage->fresh()->load(self::FULL),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /* =====================================================
     |  PAYMENTS
     ===================================================== */
    public function addPayment(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date'   => 'required|date',
            'method' => 'required|in:cash,check,card,transfer',
            'notes'  => 'nullable|string',
        ]);

        $lavage->addPayment(
            (float) $validated['amount'],
            $validated['date'],
            $validated['method'],
            $validated['notes'] ?? ''
        );
        $lavage->save();

        return response()->json(['message' => 'Paiement enregistré', 'lavage' => $lavage->fresh()->load(self::FULL)]);
    }

    public function removePayment(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        $validated = $request->validate(['payment_id' => 'required|string']);

        if (!$lavage->removePayment($validated['payment_id'])) {
            return response()->json(['message' => 'Paiement introuvable'], 404);
        }
        $lavage->save();

        return response()->json(['message' => 'Paiement supprimé', 'lavage' => $lavage->fresh()->load(self::FULL)]);
    }

    /* =====================================================
     |  PRODUCTS
     ===================================================== */
    public function addProducts(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        if ($lavage->status === 'cancelled') {
            return response()->json(['message' => 'Impossible d\'ajouter des produits à un lavage annulé'], 422);
        }

        $validated = $request->validate([
            'products'              => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity'   => 'required|integer|min:1',
            'products.*.unit_cost'  => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $rows = $this->mergeProductRows($validated['products']);
            $this->applyProducts($lavage, $rows, $this->currentUserName());

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Produits ajoutés',
            'lavage'  => $lavage->fresh()->load(self::FULL),
        ]);
    }

    public function removeProduct(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('lavage');
        $lavage = Lavage::findOrFail($id);

        $validated = $request->validate([
            'lavage_product_id' => 'required|exists:lavage_products,id',
        ]);

        $lp = LavageProduct::where('lavage_id', $lavage->id)
            ->where('id', $validated['lavage_product_id'])
            ->firstOrFail();

        try {
            DB::beginTransaction();

            $product = Product::lockForUpdate()->find($lp->product_id);
            if ($product && $product->stock_quantity !== null) {
                $product->increment('stock_quantity', $lp->quantity);
                ProductStockMovement::create([
                    'product_id'  => $product->id,
                    'type'        => 'in',
                    'quantity'    => $lp->quantity,
                    'stock_after' => $product->fresh()->stock_quantity,
                    'reason'      => 'Retrait produit du lavage #' . $lavage->id,
                    'lavage_id'   => $lavage->id,
                    'created_by'  => $this->currentUserName(),
                ]);
            }

            $lp->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Produit retiré',
            'lavage'  => $lavage->fresh()->load(self::FULL),
        ]);
    }

    /* =====================================================
     |  STATISTICS
     ===================================================== */
    public function statistics(): JsonResponse
    {
        $this->authorizePageAccess('lavage');

        $byType = Lavage::where('status', 'completed')
            ->selectRaw('type, COUNT(*) as count, SUM(price) as revenue')
            ->groupBy('type')
            ->get();

        $monthly = Lavage::where('status', 'completed')
            ->selectRaw("DATE_FORMAT(date_lavage, '%Y-%m') as month, COUNT(*) as count, SUM(price) as revenue")
            ->groupBy('month')
            ->orderByDesc('month')
            ->limit(12)
            ->get();

        $topServices = collect(Lavage::whereNotNull('services')
                ->where('status', 'completed')
                ->pluck('services'))
            ->flatten(1)
            ->groupBy('name')
            ->map(fn($group, $name) => [
                'name'    => $name,
                'count'   => $group->count(),
                'revenue' => $group->sum(fn($s) => (float) ($s['price'] ?? 0) * (int) ($s['quantity'] ?? 1)),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(10);

        return response()->json([
            'by_type'      => $byType,
            'monthly'      => $monthly,
            'top_services' => $topServices,
        ]);
    }

    /* =====================================================
     |  HELPERS
     ===================================================== */

    /**
     * Merge duplicate product_id rows so we never create two lines
     * for the same product on one request.
     */
    private function mergeProductRows(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            if (empty($row['product_id'])) continue;
            $id  = (int) $row['product_id'];
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty <= 0) continue;

            if (!isset($merged[$id])) {
                $merged[$id] = [
                    'product_id' => $id,
                    'quantity'   => 0,
                    'unit_cost'  => $row['unit_cost'] ?? null,
                ];
            }
            $merged[$id]['quantity'] += $qty;
            if (isset($row['unit_cost']) && $row['unit_cost'] !== null) {
                $merged[$id]['unit_cost'] = $row['unit_cost'];
            }
        }
        return array_values($merged);
    }

    /**
     * Consume stock for the given product rows.
     * Creates lavage_products + product_stock_movements and decrements stock.
     * Accepts nullable stock_quantity products (skips the decrement but still logs).
     */
    private function applyProducts(Lavage $lavage, array $productsData, string $createdBy): void
    {
        foreach ($productsData as $row) {
            /** @var Product $product */
            $product = Product::lockForUpdate()->find($row['product_id']);
            if (!$product) {
                throw new \Exception("Produit #{$row['product_id']} introuvable");
            }

            $qty = (int) $row['quantity'];

            // Stock guard (only if the product actually tracks stock)
            if ($product->stock_quantity !== null && $product->stock_quantity < $qty) {
                throw new \Exception(
                    "Stock insuffisant pour {$product->name} (disponible: {$product->stock_quantity})"
                );
            }

            $unitCost  = $row['unit_cost'] ?? $product->purchase_price ?? 0;
            $totalCost = (float) $unitCost * $qty;

            LavageProduct::create([
                'lavage_id'  => $lavage->id,
                'product_id' => $product->id,
                'quantity'   => $qty,
                'unit_cost'  => $unitCost,
                'total_cost' => $totalCost,
                'created_by' => $createdBy,
            ]);

            $stockAfter = $product->stock_quantity;
            if ($product->stock_quantity !== null) {
                $product->decrement('stock_quantity', $qty);
                $stockAfter = $product->fresh()->stock_quantity;
            }

            ProductStockMovement::create([
                'product_id'  => $product->id,
                'type'        => 'out',
                'quantity'    => -$qty,
                'stock_after' => $stockAfter ?? 0,
                'reason'      => 'Lavage #' . $lavage->id,
                'lavage_id'   => $lavage->id,
                'created_by'  => $createdBy,
            ]);
        }
    }

    /**
     * Restore stock for every product line of this lavage.
     * Used on cancel / delete / update-resync.
     */
    private function restoreProductStock(Lavage $lavage): void
    {
        $rows = LavageProduct::where('lavage_id', $lavage->id)->get();
        foreach ($rows as $row) {
            $product = Product::lockForUpdate()->find($row->product_id);
            if (!$product || $product->stock_quantity === null) {
                continue;
            }

            $product->increment('stock_quantity', $row->quantity);

            ProductStockMovement::create([
                'product_id'  => $product->id,
                'type'        => 'in',
                'quantity'    => $row->quantity,
                'stock_after' => $product->fresh()->stock_quantity,
                'reason'      => 'Annulation lavage #' . $lavage->id,
                'lavage_id'   => $lavage->id,
                'created_by'  => $this->currentUserName(),
            ]);
        }
    }

    /**
     * Fill washer_name (copy of the laveur's name) and laveur_amount
     * (what the laveur earns) on the data about to be saved.
     *
     * The amount is only recomputed when the laveur or the price changed,
     * so changing a laveur's rate later does not rewrite old lavages.
     */
    private function applyLaveur(array &$data, ?Lavage $lavage = null): void
    {
        $hasKey   = array_key_exists('laveur_id', $data);
        $laveurId = $hasKey ? $data['laveur_id'] : $lavage?->laveur_id;

        if (!$laveurId) {
            if ($hasKey) $data['laveur_amount'] = null; // laveur removed
            return;
        }

        $price = (float) ($data['price'] ?? $lavage?->price ?? 0);

        $laveurChanged = $lavage === null || (int) $laveurId !== (int) $lavage->laveur_id;
        $priceChanged  = $lavage !== null
            && array_key_exists('price', $data)
            && abs($price - (float) $lavage->price) > 0.001;

        if ($laveurChanged || $priceChanged || $lavage->laveur_amount === null) {
            $data['laveur_amount'] = $this->laveurAmount((int) $laveurId, $price);
        }

        if ($laveurChanged) {
            $laveur = Laveur::find($laveurId);
            if ($laveur) $data['washer_name'] = $laveur->name;
        }
    }

    private function laveurAmount(int $laveurId, float $price): float
    {
        $laveur = Laveur::find($laveurId);
        return $laveur ? $laveur->computeAmount($price) : 0.0;
    }

    /**
     * Safe fallback for created_by / auth user fullname.
     */
    private function currentUserName(): string
    {
        $u = auth()->user();
        return $u ? ($u->Fullname ?? $u->name ?? 'System') : 'System';
    }

    /**
     * Persist a base64 data URI as a public image.
     */
    private function saveBase64Image(string $data, string $dir): ?string
    {
        try {
            [$meta, $content] = explode(',', $data, 2);
            $mime = str_replace('data:', '', explode(';', $meta)[0]);
            $ext  = match ($mime) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png'               => 'png',
                'image/webp'              => 'webp',
                default                   => 'png',
            };
            $binary = base64_decode($content);
            if ($binary === false) return null;

            $path = $dir . '/' . uniqid() . '_' . time() . '.' . $ext;
            Storage::disk('public')->put($path, $binary);
            return $path;
        } catch (\Throwable $e) {
            Log::error('Lavage saveBase64Image failed: ' . $e->getMessage());
            return null;
        }
    }
}