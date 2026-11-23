<?php
// app/Http/Controllers/CarController.php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CarController extends Controller
{
    public function index(): JsonResponse
    {
        $cars = Car::with('matricules')->get()->map(function ($car) {
            // Add full image URL to each car
            if ($car->image) {
                $car->image_url = Storage::disk('public')->exists($car->image) 
                    ? Storage::disk('public')->url($car->image)
                    : null;
            } else {
                $car->image_url = null;
            }
            return $car;
        });
        
        return response()->json(['cars' => $cars]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string',
            'image' => 'nullable|string', // Keep as string for base64
            'price_per_day' => 'required|numeric|min:0',
            'status' => 'required|in:disponible,non disponible',
            'seats' => 'sometimes|integer|min:1',
            'doors' => 'sometimes|integer|min:1',
            'fuel_type' => 'sometimes|in:petrol,diesel,electric,hybrid',
            'transmission' => 'sometimes|in:manual,automatic',
        ]);

        // Handle base64 image if provided
        if (isset($validated['image']) && !empty($validated['image'])) {
            try {
                $imageData = $validated['image'];
                
                // Check if it's a base64 string or already a path
                if (strpos($imageData, 'data:image') === 0) {
                    // It's a new base64 image - upload it
                    list($type, $imageData) = explode(';', $imageData);
                    list(, $imageData) = explode(',', $imageData);
                    $imageData = base64_decode($imageData);
                    
                    // Generate unique filename
                    $filename = 'car_' . uniqid() . '.png';
                    $path = 'cars/' . $filename;
                    
                    // Store the image
                    Storage::disk('public')->put($path, $imageData);
                    
                    // Update the image path in validated data
                    $validated['image'] = $path;
                } else {
                    // It's already a stored path - keep it as is
                    $validated['image'] = $imageData;
                }
            } catch (\Exception $e) {
                // If image processing fails, set to null
                $validated['image'] = null;
                \Log::error('Image upload error: ' . $e->getMessage());
            }
        } else {
            $validated['image'] = null;
        }

        $car = Car::create($validated);
        
        // Add image URL to response
        if ($car->image) {
            $car->image_url = Storage::disk('public')->url($car->image);
        }
        
        return response()->json(['car' => $car->load('matricules')], 201);
    }

    public function show($id): JsonResponse
    {
        $car = Car::with('matricules', 'reservations', 'accidents')->findOrFail($id);
        
        // Add image URL
        if ($car->image) {
            $car->image_url = Storage::disk('public')->exists($car->image) 
                ? Storage::disk('public')->url($car->image)
                : null;
        }
        
        return response()->json(['car' => $car]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $car = Car::findOrFail($id);
        
        $validated = $request->validate([
            'brand' => 'sometimes|string',
            'model' => 'sometimes|string',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'sometimes|string',
            'image' => 'nullable|string',
            'price_per_day' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:disponible,non disponible',
            'seats' => 'sometimes|integer|min:1',
            'doors' => 'sometimes|integer|min:1',
            'fuel_type' => 'sometimes|in:petrol,diesel,electric,hybrid',
            'transmission' => 'sometimes|in:manual,automatic',
        ]);

        // Handle image processing - FIXED VERSION (similar to accident controller)
        if (isset($validated['image']) && !empty($validated['image'])) {
            try {
                $imageData = $validated['image'];
                
                // Check if it's a base64 string
                if (strpos($imageData, 'data:image') === 0) {
                    // New base64 image - upload it
                    list($type, $imageData) = explode(';', $imageData);
                    list(, $imageData) = explode(',', $imageData);
                    $imageData = base64_decode($imageData);
                    
                    // Generate unique filename
                    $filename = 'car_' . uniqid() . '.png';
                    $path = 'cars/' . $filename;
                    
                    // Store the image
                    Storage::disk('public')->put($path, $imageData);
                    
                    // Update the image path in validated data
                    $validated['image'] = $path;
                    
                    // Delete old image if exists
                    if ($car->image && Storage::disk('public')->exists($car->image)) {
                        Storage::disk('public')->delete($car->image);
                    }
                } else {
                    // Existing image path - keep it as is
                    $validated['image'] = $imageData;
                }
            } catch (\Exception $e) {
                // If image processing fails, keep the old image
                unset($validated['image']);
                \Log::error('Image upload error: ' . $e->getMessage());
            }
        } elseif (isset($validated['image']) && empty($validated['image'])) {
            // If empty image string provided, delete the current image
            if ($car->image && Storage::disk('public')->exists($car->image)) {
                Storage::disk('public')->delete($car->image);
            }
            $validated['image'] = null;
        } else {
            // Keep existing image if not updating this field
            $validated['image'] = $car->image;
        }

        $car->update($validated);
        
        // Add image URL to response
        if ($car->image) {
            $car->image_url = Storage::disk('public')->url($car->image);
        }
        
        return response()->json(['car' => $car->load('matricules')]);
    }

    public function destroy($id): JsonResponse
    {
        $car = Car::findOrFail($id);
        
        // Delete associated image
        if ($car->image && Storage::disk('public')->exists($car->image)) {
            Storage::disk('public')->delete($car->image);
        }
        
        $car->delete();
        return response()->json(['message' => 'Car deleted successfully']);
    }

    // New method to assign a matricule to a car
    public function assignMatricule(Request $request, $id): JsonResponse
    {
        $car = Car::findOrFail($id);
        
        $validated = $request->validate([
            'matricule_id' => 'required|exists:matricules,id'
        ]);

        // Update the matricule to belong to this car
        $matricule = \App\Models\Matricule::findOrFail($validated['matricule_id']);
        $matricule->update(['car_id' => $car->id]);

        return response()->json([
            'message' => 'Matricule assigned successfully',
            'car' => $car->load('matricules')
        ]);
    }

    // New method to get current active matricule
    public function getCurrentMatricule($id): JsonResponse
    {
        $car = Car::findOrFail($id);
        $currentMatricule = $car->currentMatricule();
        
        return response()->json(['current_matricule' => $currentMatricule]);
    }
}