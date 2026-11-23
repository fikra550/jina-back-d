<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Traits\PagePermissionHelper;

class ProductCategoryController extends Controller
{
    use PagePermissionHelper;

    public function index(): JsonResponse
    {
        $this->authorizePageAccess('products');

        $categories = ProductCategory::ordered()
            ->withCount('products')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePageAccess('products');

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:product_categories',
            'slug'        => 'nullable|string|max:255|unique:product_categories',
            'color'       => 'nullable|string|max:20',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        $category = ProductCategory::create($validated);
        $category->loadCount('products');

        return response()->json(['category' => $category], 201);
    }

    public function show($id): JsonResponse
    {
        $this->authorizePageAccess('products');
        $category = ProductCategory::withCount('products')->findOrFail($id);
        return response()->json(['category' => $category]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('products');
        $category = ProductCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:product_categories,name,' . $id,
            'slug'        => 'nullable|string|max:255|unique:product_categories,slug,' . $id,
            'color'       => 'nullable|string|max:20',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        $category->update($validated);
        $category->loadCount('products');

        return response()->json(['category' => $category]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $this->authorizePageAccess('products');
        $category = ProductCategory::withCount('products')->findOrFail($id);

        $force  = $request->boolean('force', false);
        $moveTo = $request->input('move_to_category_id');

        if ($category->products_count > 0) {
            if (!$force) {
                return response()->json([
                    'message'        => "Impossible de supprimer : {$category->products_count} produit(s) utilisent cette catégorie.",
                    'products_count' => $category->products_count,
                ], 422);
            }

            try {
                DB::beginTransaction();

                if ($moveTo) {
                    $target = ProductCategory::find($moveTo);
                    if (!$target) {
                        DB::rollBack();
                        return response()->json(['message' => 'Catégorie cible introuvable'], 422);
                    }
                    $category->products()->update(['category_id' => $target->id]);
                } else {
                    $category->products()->update(['category_id' => null]);
                }

                $category->delete();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 500);
            }
        } else {
            $category->delete();
        }

        return response()->json(['message' => 'Catégorie supprimée']);
    }
}