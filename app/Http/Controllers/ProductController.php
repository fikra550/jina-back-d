<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductStockMovement;
use App\Services\ProductVolume;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\PagePermissionHelper;

class ProductController extends Controller
{
    use PagePermissionHelper;

    /* =====================================================
     |  PRODUCTS
     ===================================================== */
    public function index(): JsonResponse
    {
        $this->authorizePageAccess('products');

        $products = Product::with('category')
            ->withSum('sales as total_sold', 'quantity')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                $p->image_url    = $p->image ? Storage::disk('public')->url($p->image) : null;
                $p->total_volume = ProductVolume::total($p);
                return $p;
            });

        $stats = [
            'total_products'    => $products->count(),
            'low_stock'         => $products->where('is_low_stock', true)->count(),
            'out_of_stock'      => $products->where('stock_quantity', '<=', 0)->count(),
            'inventory_value'   => $products->sum(fn($p) => $p->purchase_price * $p->stock_quantity),
            'potential_revenue' => $products->sum(fn($p) => $p->selling_price * $p->stock_quantity),
        ];

        return response()->json(['products' => $products, 'stats' => $stats]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePageAccess('products');

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'reference'       => 'nullable|string|unique:products',
            'description'     => 'nullable|string',
            'category_id'     => 'required|exists:product_categories,id',
            'unit'            => 'sometimes|string|max:50',
            'volume_per_unit' => 'nullable|numeric|min:0.01',
            'open_volume'     => 'nullable|numeric|min:0',
            'purchase_price'  => 'required|numeric|min:0',
            'selling_price'   => 'required|numeric|min:0',
            'stock_quantity'  => 'sometimes|integer|min:0',
            'alert_threshold' => 'sometimes|integer|min:0',
            'image'           => 'nullable|string',
            'is_active'       => 'sometimes|boolean',
        ]);

        $this->normalizeVolume($validated);

        if (!empty($validated['image']) && str_starts_with($validated['image'], 'data:')) {
            $validated['image'] = $this->saveBase64Image($validated['image'], 'products');
        }

        $product = Product::create($validated);

        if ($product->stock_quantity > 0) {
            ProductStockMovement::create([
                'product_id'  => $product->id,
                'type'        => 'in',
                'quantity'    => $product->stock_quantity,
                'stock_after' => $product->stock_quantity,
                'reason'      => 'Stock initial',
                'created_by'  => auth()->user()->Fullname ?? 'System',
            ]);
        }

        $product->load('category');
        $product->total_volume = ProductVolume::total($product);

        return response()->json(['product' => $product], 201);
    }

    public function show($id): JsonResponse
    {
        $this->authorizePageAccess('products');
        $product = Product::with(['category', 'sales.client'])->findOrFail($id);
        $product->image_url    = $product->image ? Storage::disk('public')->url($product->image) : null;
        $product->total_volume = ProductVolume::total($product);
        return response()->json(['product' => $product]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('products');
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'reference'       => 'nullable|string|unique:products,reference,' . $id,
            'stock_reason'    => 'nullable|string|max:255',
            'description'     => 'nullable|string',
            'category_id'     => 'sometimes|exists:product_categories,id',
            'unit'            => 'sometimes|string|max:50',
            'volume_per_unit' => 'nullable|numeric|min:0.01',
            'open_volume'     => 'nullable|numeric|min:0',
            'purchase_price'  => 'sometimes|numeric|min:0',
            'selling_price'   => 'sometimes|numeric|min:0',
            'stock_quantity'  => 'sometimes|integer|min:0',
            'alert_threshold' => 'sometimes|integer|min:0',
            'image'           => 'nullable|string',
            'is_active'       => 'sometimes|boolean',
        ]);

        $stockReason = $validated['stock_reason'] ?? null;
        unset($validated['stock_reason']);

        $this->normalizeVolume($validated);

        if (array_key_exists('image', $validated)) {
            if (!empty($validated['image']) && str_starts_with($validated['image'], 'data:')) {
                $validated['image'] = $this->saveBase64Image($validated['image'], 'products');
                if ($product->image) Storage::disk('public')->delete($product->image);
            } elseif (empty($validated['image']) && $product->image) {
                Storage::disk('public')->delete($product->image);
                $validated['image'] = null;
            }
        }

        if (isset($validated['stock_quantity']) && $validated['stock_quantity'] != $product->stock_quantity) {
            $diff = $validated['stock_quantity'] - $product->stock_quantity;
            ProductStockMovement::create([
                'product_id'  => $product->id,
                'type'        => 'adjustment',
                'quantity'    => $diff,
                'stock_after' => $validated['stock_quantity'],
                'reason'      => $stockReason ?: 'Ajustement manuel',
                'created_by'  => auth()->user()->Fullname ?? 'System',
            ]);
        }

        $product->update($validated);
        $product->load('category');
        $product->total_volume = ProductVolume::total($product);

        return response()->json(['product' => $product]);
    }

    public function destroy($id): JsonResponse
    {
        $this->authorizePageAccess('products');
        $product = Product::findOrFail($id);
        if ($product->image) Storage::disk('public')->delete($product->image);
        $product->delete();
        return response()->json(['message' => 'Produit supprimé']);
    }

    /* =====================================================
     |  SALES
     ===================================================== */
    public function sales(Request $request): JsonResponse
    {
        $this->authorizePageAccess('products');

        $query = ProductSale::with(['product.category', 'client', 'car', 'matricule'])
            ->orderBy('sale_date', 'desc');

        if ($request->filled('from')) $query->where('sale_date', '>=', $request->from);
        if ($request->filled('to'))   $query->where('sale_date', '<=', $request->to);

        $sales = $query->get();

        $stats = [
            'total_sales'   => $sales->count(),
            'total_revenue' => $sales->sum('total_price'),
            'unpaid'        => $sales->sum(fn($s) => $s->total_price - $s->amount_paid),
        ];

        return response()->json(['sales' => $sales, 'stats' => $stats]);
    }

    public function recordSale(Request $request): JsonResponse
    {
        $this->authorizePageAccess('products');

        $validated = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'client_id'      => 'nullable|exists:clients,id',
            'client_name'    => 'nullable|string|max:255',
            'car_id'         => 'nullable|exists:cars,id',
            'matricule_id'   => 'nullable|exists:matricules,id',
            'quantity'       => 'required|integer|min:1',
            'unit_price'     => 'required|numeric|min:0',
            'payment_status' => 'sometimes|in:paid,unpaid,partial',
            'amount_paid'    => 'sometimes|numeric|min:0',
            'notes'          => 'nullable|string',
            'sale_date'      => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->stock_quantity < $validated['quantity']) {
                DB::rollBack();
                return response()->json([
                    'message' => "Stock insuffisant. Disponible: {$product->stock_quantity} {$product->unit}",
                ], 422);
            }

            $total = $validated['quantity'] * $validated['unit_price'];
            $validated['total_price'] = $total;

            if (!isset($validated['amount_paid'])) {
                $validated['amount_paid'] = ($validated['payment_status'] ?? 'paid') === 'paid' ? $total : 0;
            }
            if (!isset($validated['payment_status'])) {
                if ($validated['amount_paid'] <= 0) $validated['payment_status'] = 'unpaid';
                elseif ($validated['amount_paid'] >= $total) $validated['payment_status'] = 'paid';
                else $validated['payment_status'] = 'partial';
            }

            $validated['created_by'] = auth()->user()->Fullname ?? 'System';

            $sale = ProductSale::create($validated);

            $product->decrement('stock_quantity', $validated['quantity']);

            ProductStockMovement::create([
                'product_id'      => $product->id,
                'type'            => 'out',
                'quantity'        => -$validated['quantity'],
                'stock_after'     => $product->fresh()->stock_quantity,
                'reason'          => 'Vente',
                'product_sale_id' => $sale->id,
                'created_by'      => $validated['created_by'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Vente enregistrée',
                'sale'    => $sale->load(['product', 'client']),
                'product' => $product->fresh(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product sale error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function updateSale(Request $request, $saleId): JsonResponse
    {
        $this->authorizePageAccess('products');

        $sale = ProductSale::findOrFail($saleId);

        $validated = $request->validate([
            'payment_status' => 'sometimes|in:paid,unpaid,partial',
            'amount_paid'    => 'sometimes|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $sale->update($validated);
        return response()->json(['sale' => $sale->load('product', 'client')]);
    }

    public function deleteSale($saleId): JsonResponse
    {
        $this->authorizePageAccess('products');

        try {
            DB::beginTransaction();
            $sale = ProductSale::findOrFail($saleId);

            $product = $sale->product;
            $product->increment('stock_quantity', $sale->quantity);

            ProductStockMovement::create([
                'product_id'  => $product->id,
                'type'        => 'in',
                'quantity'    => $sale->quantity,
                'stock_after' => $product->fresh()->stock_quantity,
                'reason'      => 'Annulation vente #' . $sale->id,
                'created_by'  => auth()->user()->Fullname ?? 'System',
            ]);

            $sale->delete();
            DB::commit();

            return response()->json(['message' => 'Vente supprimée et stock restauré']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function stockMovements($id): JsonResponse
    {
        $this->authorizePageAccess('products');

        $movements = ProductStockMovement::where('product_stock_movements.product_id', $id)
            ->leftJoin('product_sales', 'product_sales.id', '=', 'product_stock_movements.product_sale_id')
            ->leftJoin('lavages', 'lavages.id', '=', 'product_stock_movements.lavage_id')
            ->select(
                'product_stock_movements.*',
                DB::raw('COALESCE(product_sales.client_name, lavages.client_name) as client_name')
            )
            ->orderByDesc('product_stock_movements.created_at')
            ->orderByDesc('product_stock_movements.id')
            ->get();

        return response()->json(['movements' => $movements]);
    }

    /* =====================================================
     |  HELPER
     ===================================================== */
    /**
     * Cohérence volume_per_unit / open_volume.
     */
    private function normalizeVolume(array &$v): void
    {
        if (array_key_exists('volume_per_unit', $v) && empty($v['volume_per_unit'])) {
            $v['volume_per_unit'] = null;
            $v['open_volume']     = 0;
            return;
        }

        if (!empty($v['volume_per_unit'])) {
            $open = (float) ($v['open_volume'] ?? 0);
            if ($open >= (float) $v['volume_per_unit']) {
                throw ValidationException::withMessages([
                    'open_volume' => 'Le volume entamé doit être inférieur à la contenance par unité.',
                ]);
            }
            $v['open_volume'] = $open;
        }
    }

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
            Log::error('saveBase64Image failed: ' . $e->getMessage());
            return null;
        }
    }
}