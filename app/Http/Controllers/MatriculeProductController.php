<?php

namespace App\Http\Controllers;

use App\Models\Matricule;
use App\Models\MatriculeProduct;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Services\ProductVolume;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\PagePermissionHelper;

class MatriculeProductController extends Controller
{
    use PagePermissionHelper;

    /* =====================================================
     |  LIST PRODUCTS OF A MATRICULE
     ===================================================== */
    public function index($matriculeId): JsonResponse
    {
        $this->authorizePageAccess('matricules');

        $matricule = Matricule::with('products.product')->findOrFail($matriculeId);

        return response()->json([
            'matricule' => $matricule->only(['id', 'matricule_code', 'car_id', 'status']),
            'products'  => $matricule->products,
            'total'     => (float) $matricule->products->sum('total_cost'),
        ]);
    }

    /* =====================================================
     |  ADD PRODUCTS (deducts stock)
     ===================================================== */
    public function store(Request $request, $matriculeId): JsonResponse
    {
        $this->authorizePageAccess('matricules');

        $validated = $request->validate([
            'products'                       => 'required|array|min:1',
            'products.*.product_id'          => 'required|exists:products,id',
            'products.*.quantity'            => 'nullable|integer|min:1',
            'products.*.volume'              => 'nullable|numeric|min:0.01', // litres (produits liquides)
            'products.*.unit_cost'           => 'nullable|numeric|min:0',
            'products.*.purpose'             => 'nullable|in:maintenance,repair,accessory,other',
            'products.*.notes'               => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $matricule = Matricule::findOrFail($matriculeId);
            $user      = auth()->user();
            $addedBy   = $user ? ($user->Fullname ?? $user->name ?? 'System') : 'System';

            $created = [];

            foreach ($validated['products'] as $row) {
                $product = Product::lockForUpdate()->findOrFail($row['product_id']);

                /* ---------- Produit liquide : on saisit les LITRES utilisés ---------- */
                if (ProductVolume::isLiquid($product)) {
                    $liters = round((float) ($row['volume'] ?? 0), 2);

                    if ($liters <= 0) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Indiquez les litres utilisés pour « {$product->name} »",
                        ], 422);
                    }

                    $available = ProductVolume::total($product);
                    if ($liters > $available + 0.001) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Quantité insuffisante pour « {$product->name} » (disponible: {$available} L, demandé: {$liters} L)",
                        ], 422);
                    }

                    // unit_cost = prix par LITRE
                    $unitCost  = isset($row['unit_cost'])
                        ? (float) $row['unit_cost']
                        : round((float) ($product->purchase_price ?? 0) / (float) $product->volume_per_unit, 4);
                    $totalCost = round($unitCost * $liters, 2);

                    $unitsOpened = ProductVolume::consumeAndLog(
                        $product,
                        $liters,
                        "Matricule {$matricule->matricule_code} — {$liters} L",
                        $addedBy
                    );

                    $mp = MatriculeProduct::create([
                        'matricule_id' => $matricule->id,
                        'product_id'   => $product->id,
                        'quantity'     => $unitsOpened,
                        'volume_used'  => $liters,
                        'unit_cost'    => $unitCost,
                        'total_cost'   => $totalCost,
                        'purpose'      => $row['purpose'] ?? 'maintenance',
                        'notes'        => $row['notes'] ?? null,
                        'added_by'     => $addedBy,
                    ]);

                    $created[] = $mp->load('product');
                    continue;
                }

                /* ---------- Produit à l'unité ---------- */
                $qty = (int) ($row['quantity'] ?? 0);
                if ($qty < 1) {
                    DB::rollBack();
                    return response()->json(['message' => "Indiquez la quantité pour « {$product->name} »"], 422);
                }

                if ($product->stock_quantity < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Stock insuffisant pour « {$product->name} » (dispo: {$product->stock_quantity}, demandé: {$qty})",
                    ], 422);
                }

                $unitCost  = isset($row['unit_cost'])
                    ? (float) $row['unit_cost']
                    : (float) ($product->purchase_price ?? 0);
                $totalCost = $unitCost * $qty;

                $mp = MatriculeProduct::create([
                    'matricule_id' => $matricule->id,
                    'product_id'   => $product->id,
                    'quantity'     => $qty,
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $totalCost,
                    'purpose'      => $row['purpose'] ?? 'maintenance',
                    'notes'        => $row['notes'] ?? null,
                    'added_by'     => $addedBy,
                ]);

                $product->decrement('stock_quantity', $qty);

                ProductStockMovement::create([
                    'product_id'  => $product->id,
                    'type'        => 'out',
                    'quantity'    => -$qty,
                    'stock_after' => $product->fresh()->stock_quantity,
                    'reason'      => "Matricule {$matricule->matricule_code}",
                    'created_by'  => $addedBy,
                ]);

                $created[] = $mp->load('product');
            }

            DB::commit();

            return response()->json([
                'message'   => 'Produits ajoutés au matricule',
                'products'  => $created,
                'matricule' => $matricule->fresh()->load('products.product'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MatriculeProduct store failed: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /* =====================================================
     |  REMOVE ONE ROW (restores stock)
     ===================================================== */
    public function destroy($matriculeId, $matriculeProductId): JsonResponse
    {
        $this->authorizePageAccess('matricules');

        DB::beginTransaction();

        try {
            $mp = MatriculeProduct::where('matricule_id', $matriculeId)
                ->findOrFail($matriculeProductId);

            $product = Product::lockForUpdate()->find($mp->product_id);
            $by      = auth()->user()?->Fullname ?? 'System';

            if ($product) {
                if ($mp->volume_used !== null && ProductVolume::isLiquid($product)) {
                    ProductVolume::restoreAndLog(
                        $product,
                        (float) $mp->volume_used,
                        "Annulation produit matricule #{$mp->matricule_id} — {$mp->volume_used} L",
                        $by
                    );
                } else {
                    $product->increment('stock_quantity', $mp->quantity);

                    ProductStockMovement::create([
                        'product_id'  => $product->id,
                        'type'        => 'in',
                        'quantity'    => $mp->quantity,
                        'stock_after' => $product->fresh()->stock_quantity,
                        'reason'      => "Annulation produit matricule #{$mp->matricule_id}",
                        'created_by'  => $by,
                    ]);
                }
            }

            $mp->delete();
            DB::commit();

            return response()->json(['message' => 'Produit retiré du matricule, stock restauré.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MatriculeProduct destroy failed: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
}