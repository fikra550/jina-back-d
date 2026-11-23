<?php
// app/Http/Controllers/GarageController.php

namespace App\Http\Controllers;

use App\Models\Garage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GarageController extends Controller
{
    public function index(): JsonResponse
    {
        $garages = Garage::all();
        return response()->json(['garages' => $garages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'rc' => 'nullable|string|max:50',
            'if' => 'nullable|string|max:50',
            'ice' => 'nullable|string|max:50',
            'tp' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $garage = Garage::create($validated);
        return response()->json(['garage' => $garage], 201);
    }

    public function show($id): JsonResponse
    {
        $garage = Garage::findOrFail($id);
        return response()->json(['garage' => $garage]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $garage = Garage::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'rc' => 'nullable|string|max:50',
            'if' => 'nullable|string|max:50',
            'ice' => 'nullable|string|max:50',
            'tp' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $garage->update($validated);
        return response()->json(['garage' => $garage]);
    }

    public function destroy($id): JsonResponse
    {
        $garage = Garage::findOrFail($id);
        $garage->delete();
        return response()->json(['message' => 'Garage deleted successfully']);
    }
}