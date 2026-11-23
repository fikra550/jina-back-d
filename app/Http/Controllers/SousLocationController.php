<?php

namespace App\Http\Controllers;

use App\Models\SousLocation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SousLocationController extends Controller
{
    public function index(): JsonResponse
{
    try {
        $sousLocations = SousLocation::withCount('reservations')->get();
        return response()->json(['sous_locations' => $sousLocations]);
    } catch (\Exception $e) {
        \Log::error('SousLocation index error: ' . $e->getMessage());
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}

    public function show($id): JsonResponse
    {
        $sousLocation = SousLocation::with('reservations.client', 'reservations.car', 'reservations.matricule')
                                    ->findOrFail($id);
        return response()->json(['sous_location' => $sousLocation]);
    }
    public function store(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive'
        ]);

        // Définir la valeur par défaut si non fournie
        $validated['status'] = $validated['status'] ?? 'active';

        $sousLocation = SousLocation::create($validated);
        return response()->json(['sous_location' => $sousLocation], 201);
    } catch (\Exception $e) {
        \Log::error('SousLocation store error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function update(Request $request, $id): JsonResponse
    {
        $sousLocation = SousLocation::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive'
        ]);
        $sousLocation->update($validated);
        return response()->json(['sous_location' => $sousLocation]);
    }

    public function destroy($id): JsonResponse
    {
        $sousLocation = SousLocation::findOrFail($id);
        $sousLocation->delete();
        return response()->json(['message' => 'Sous-location supprimée']);
    }
}