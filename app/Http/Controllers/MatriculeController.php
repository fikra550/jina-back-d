<?php
// app/Http/Controllers/MatriculeController.php

namespace App\Http\Controllers;

use App\Models\Matricule;
use App\Models\Car;
use App\Models\Product;
use App\Models\MatriculeProduct;
use App\Models\ProductStockMovement;
use App\Services\ProductVolume;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MatriculeController extends Controller
{
    public function index(): JsonResponse
    {
        $matricules = Matricule::with('car')->get();
        return response()->json(['matricules' => $matricules]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'car_id' => 'required|exists:cars,id',
            'matricule_code' => 'required|string|unique:matricules',
            'kilometrage' => 'required|numeric|min:0',
            // --------------------------------------------------------------
            // ✅ UPDATED: include 'sold' in the allowed status values
            // --------------------------------------------------------------
            'status' => 'required|in:active,inactive,sold',
            'visit_tech' => 'required|date',
            'date_taxe_voiture' => 'nullable|date',
            'date_assurance' => 'nullable|date',
            'vidange_status' => 'required|in:done,not done',
            
            // Maintenance validation
            'paquets_de_voiture' => 'nullable|in:yes,no',
            'paquets_de_voiture_date' => 'nullable|date',
            'paquets_de_voiture_count' => 'nullable|integer|min:0',
            'paquets_de_voiture_history' => 'nullable|array',
            
            'paquets_de_frein' => 'nullable|in:yes,no',
            'paquets_de_frein_date' => 'nullable|date',
            'paquets_de_frein_count' => 'nullable|integer|min:0',
            'paquets_de_frein_history' => 'nullable|array',
            
            'filter_oil' => 'nullable|in:yes,no',
            'filter_oil_date' => 'nullable|date',
            'filter_oil_count' => 'nullable|integer|min:0',
            'filter_oil_history' => 'nullable|array',
            
            'filter_air' => 'nullable|in:yes,no',
            'filter_air_date' => 'nullable|date',
            'filter_air_count' => 'nullable|integer|min:0',
            'filter_air_history' => 'nullable|array',
            
            'ad_blue' => 'nullable|in:yes,no',
            'ad_blue_date' => 'nullable|date',
            'ad_blue_quantity' => 'nullable|numeric|min:0',
            'ad_blue_history' => 'nullable|array',
            
            'oil' => 'nullable|in:yes,no',
            'oil_date' => 'nullable|date',
            'oil_quantity' => 'nullable|numeric|min:0',
            'oil_history' => 'nullable|array',
            
            // Additional maintenance
            'additional_maintenance' => 'nullable|array',
            'periodic_km_maintenance' => 'nullable|array',
            
            'last_vidange_km' => 'nullable|numeric|min:0',
            'vidange_interval' => 'nullable|numeric|min:0',
        ]);

        $matricule = Matricule::create($validated);
        
        if ($matricule->car) {
            $matricule->car->updateStatusBasedOnMatricules();
        }
        
        return response()->json(['matricule' => $matricule->load('car')], 201);
    }

    public function show($id): JsonResponse
    {
        $matricule = Matricule::with('car', 'reservations', 'accidents')->findOrFail($id);
        return response()->json(['matricule' => $matricule]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'car_id' => 'sometimes|exists:cars,id',
            'matricule_code' => 'sometimes|string|unique:matricules,matricule_code,' . $id,
            'kilometrage' => 'sometimes|numeric|min:0',
            // --------------------------------------------------------------
            // ✅ UPDATED: include 'sold' in the allowed status values
            // --------------------------------------------------------------
            'status' => 'sometimes|in:active,inactive,sold',
            'visit_tech' => 'sometimes|date',
            'date_taxe_voiture' => 'nullable|date',
            'date_assurance' => 'nullable|date',
            'vidange_status' => 'sometimes|in:done,not done',
            
            // Maintenance validation
            'paquets_de_voiture' => 'nullable|in:yes,no',
            'paquets_de_voiture_date' => 'nullable|date',
            'paquets_de_voiture_count' => 'nullable|integer|min:0',
            'paquets_de_voiture_history' => 'nullable|array',
            
            'paquets_de_frein' => 'nullable|in:yes,no',
            'paquets_de_frein_date' => 'nullable|date',
            'paquets_de_frein_count' => 'nullable|integer|min:0',
            'paquets_de_frein_history' => 'nullable|array',
            
            'filter_oil' => 'nullable|in:yes,no',
            'filter_oil_date' => 'nullable|date',
            'filter_oil_count' => 'nullable|integer|min:0',
            'filter_oil_history' => 'nullable|array',
            
            'filter_air' => 'nullable|in:yes,no',
            'filter_air_date' => 'nullable|date',
            'filter_air_count' => 'nullable|integer|min:0',
            'filter_air_history' => 'nullable|array',
            
            'ad_blue' => 'nullable|in:yes,no',
            'ad_blue_date' => 'nullable|date',
            'ad_blue_quantity' => 'nullable|numeric|min:0',
            'ad_blue_history' => 'nullable|array',
            
            'oil' => 'nullable|in:yes,no',
            'oil_date' => 'nullable|date',
            'oil_quantity' => 'nullable|numeric|min:0',
            'oil_history' => 'nullable|array',
            
            // Additional maintenance
            'additional_maintenance' => 'nullable|array',
            'periodic_km_maintenance' => 'nullable|array',
            
            'last_vidange_km' => 'nullable|numeric|min:0',
            'vidange_interval' => 'nullable|numeric|min:0',
        ]);

        $oldCarId = $matricule->car_id;
        
        // UPDATED: Reset only oil and filter_oil when km increases by 10,000
        if (isset($validated['kilometrage']) && $validated['kilometrage'] - $matricule->kilometrage >= 10000) {
            $validated['vidange_status'] = 'not done';
            // Reset only required items for vidange
            $validated['oil'] = 'no';
            $validated['filter_oil'] = 'no';
            // Optional items remain unchanged
        }
        
        $matricule->update($validated);
        
        $matricule->updateVidangeStatus();
        
        if (isset($validated['car_id']) && $validated['car_id'] != $oldCarId) {
            $oldCar = Car::find($oldCarId);
            if ($oldCar) {
                $oldCar->updateStatusBasedOnMatricules();
            }
            
            $newCar = Car::find($validated['car_id']);
            if ($newCar) {
                $newCar->updateStatusBasedOnMatricules();
            }
        } else {
            if ($matricule->car) {
                $matricule->car->updateStatusBasedOnMatricules();
            }
        }
        
        return response()->json(['matricule' => $matricule->load('car')]);
    }

    public function destroy($id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        $car = $matricule->car;
        
        $matricule->delete();
        
        if ($car) {
            $car->updateStatusBasedOnMatricules();
        }
        
        return response()->json(['message' => 'Matricule deleted successfully']);
    }

    // Add maintenance with quantity (+ stock deduction when a product is chosen)
    public function addMaintenanceWithQuantity(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);

        $validated = $request->validate([
            'type'       => 'required|in:oil,ad_blue,filter_oil,filter_air,paquets_de_frein,paquets_de_voiture',
            'quantity'   => 'required_if:type,oil,ad_blue|numeric|min:0.1',
            'date'       => 'required|date',
            'product_id' => 'nullable|exists:products,id',
            'unit_cost'  => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($matricule, $validated) {
                if (!empty($validated['product_id'])) {
                    $this->consumeStockForMaintenance($matricule, $validated);
                }

                switch ($validated['type']) {
                    case 'oil':
                        $matricule->addOilChange($validated['quantity'], $validated['date']);
                        break;
                    case 'ad_blue':
                        $matricule->addAdBlue($validated['quantity'], $validated['date']);
                        break;
                    case 'filter_oil':
                        $matricule->addFilterOilChange($validated['date']);
                        break;
                    case 'filter_air':
                        $matricule->addFilterAirChange($validated['date']);
                        break;
                    case 'paquets_de_frein':
                        $matricule->addBrakePadsChange($validated['date']);
                        break;
                    case 'paquets_de_voiture':
                        $matricule->addCarPadsChange($validated['date']);
                        break;
                }
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'   => ucfirst(str_replace('_', ' ', $validated['type'])) . ' added successfully',
            'matricule' => $matricule->fresh()->load('car'),
        ]);
    }

    /**
     * Déduit le stock, journalise le mouvement et lie le produit au matricule.
     */
    private function consumeStockForMaintenance(Matricule $matricule, array $v): void
    {
        $product      = Product::lockForUpdate()->findOrFail($v['product_id']);
        $user         = auth()->user();
        $by           = $user ? ($user->Fullname ?? $user->name ?? 'System') : 'System';
        $isVolumeType = in_array($v['type'], ['oil', 'ad_blue']);
        $reason       = "Matricule {$matricule->matricule_code} — maintenance {$v['type']}";

        /* ---------- Produit liquide : quantité saisie = litres ---------- */
        if (ProductVolume::isLiquid($product)) {
            if (!$isVolumeType) {
                throw new \DomainException("« {$product->name} » est un produit liquide : choisissez un produit à l'unité pour ce type de maintenance.");
            }

            $liters    = round((float) $v['quantity'], 2);
            $available = ProductVolume::total($product);

            if ($liters > $available + 0.001) {
                throw new \DomainException("Quantité insuffisante pour « {$product->name} » (disponible: {$available} L, demandé: {$liters} L)");
            }

            $unitCost = isset($v['unit_cost'])
                ? (float) $v['unit_cost']
                : round((float) ($product->purchase_price ?? 0) / (float) $product->volume_per_unit, 4);

            $unitsOpened = ProductVolume::consumeAndLog($product, $liters, $reason, $by);

            MatriculeProduct::create([
                'matricule_id' => $matricule->id,
                'product_id'   => $product->id,
                'quantity'     => $unitsOpened,
                'volume_used'  => $liters,
                'unit_cost'    => $unitCost,
                'total_cost'   => round($unitCost * $liters, 2),
                'purpose'      => 'maintenance',
                'notes'        => $reason,
                'added_by'     => $by,
            ]);
            return;
        }

        /* ---------- Produit à l'unité (huile/AdBlue sans volume => arrondi au supérieur) ---------- */
        $qty = $isVolumeType ? (int) ceil((float) $v['quantity']) : 1;

        if ($product->stock_quantity < $qty) {
            throw new \DomainException("Stock insuffisant pour « {$product->name} » (dispo: {$product->stock_quantity}, demandé: {$qty})");
        }

        $unitCost = isset($v['unit_cost']) ? (float) $v['unit_cost'] : (float) ($product->purchase_price ?? 0);

        MatriculeProduct::create([
            'matricule_id' => $matricule->id,
            'product_id'   => $product->id,
            'quantity'     => $qty,
            'unit_cost'    => $unitCost,
            'total_cost'   => $unitCost * $qty,
            'purpose'      => 'maintenance',
            'notes'        => $reason,
            'added_by'     => $by,
        ]);

        $product->decrement('stock_quantity', $qty);

        ProductStockMovement::create([
            'product_id'  => $product->id,
            'type'        => 'out',
            'quantity'    => -$qty,
            'stock_after' => $product->fresh()->stock_quantity,
            'reason'      => $reason,
            'created_by'  => $by,
        ]);
    }

    // NEW: Get maintenance history
    public function getMaintenanceHistory($id, $type): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validTypes = ['oil', 'ad_blue', 'filter_oil', 'filter_air', 'paquets_de_frein', 'paquets_de_voiture'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid maintenance type'], 400);
        }

        $method = 'get' . ucfirst(str_replace('_', '', $type)) . 'History';
        
        if (!method_exists($matricule, $method)) {
            return response()->json(['error' => 'History method not found'], 404);
        }

        $history = $matricule->$method();
        
        return response()->json([
            'history' => $history,
            'total_quantity' => in_array($type, ['oil', 'ad_blue']) ? $matricule->{$type . '_quantity'} : null,
            'total_count' => in_array($type, ['filter_oil', 'filter_air', 'paquets_de_frein', 'paquets_de_voiture']) ? $matricule->{$type . '_count'} : null
        ]);
    }

    // NEW: Remove maintenance history entry
    public function removeMaintenanceHistoryEntry(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'type' => 'required|in:oil,ad_blue,filter_oil,filter_air,paquets_de_frein,paquets_de_voiture',
            'entry_id' => 'required|string'
        ]);

        $success = $matricule->removeMaintenanceHistoryEntry($validated['type'], $validated['entry_id']);
        
        if (!$success) {
            return response()->json(['error' => 'History entry not found'], 404);
        }

        return response()->json([
            'message' => 'History entry removed successfully',
            'matricule' => $matricule->load('car')
        ]);
    }

    /**
     * NEW: Add periodic kilometer maintenance item
     */
    public function addPeriodicKmMaintenance(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'interval_km' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
            'required_for_vidange' => 'boolean'
        ]);

        $matricule->addPeriodicKmMaintenance(
            $validated['name'],
            $validated['interval_km'],
            $validated['notes'] ?? '',
            $validated['required_for_vidange'] ?? false
        );

        return response()->json([
            'message' => 'Periodic kilometer maintenance item added successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * NEW: Add change to periodic kilometer maintenance
     */
    public function addPeriodicKmChange(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'item_id' => 'required|string',
            'date' => 'nullable|date'
        ]);

        $success = $matricule->addPeriodicKmChange(
            $validated['item_id'],
            $validated['date'] ?? null
        );

        if (!$success) {
            return response()->json([
                'message' => 'Periodic kilometer maintenance item not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Periodic kilometer maintenance change recorded successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * NEW: Get periodic kilometer maintenance history
     */
    public function getPeriodicKmHistory($id, $itemId): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $history = $matricule->getPeriodicKmHistory($itemId);
        
        if ($history->isEmpty()) {
            $item = collect($matricule->periodic_km_maintenance ?? [])->firstWhere('id', $itemId);
            
            if (!$item) {
                return response()->json(['error' => 'Periodic kilometer maintenance item not found'], 404);
            }
        }

        $item = collect($matricule->periodic_km_maintenance ?? [])->firstWhere('id', $itemId);
        
        return response()->json([
            'history' => $history,
            'item' => $item,
            'total_changes' => $item['change_count'] ?? 0,
            'next_change_km' => $item['next_change_km'] ?? 0,
            'km_remaining' => max(0, ($item['next_change_km'] ?? 0) - $matricule->kilometrage)
        ]);
    }

    /**
     * NEW: Remove periodic kilometer maintenance history entry
     */
    public function removePeriodicKmHistoryEntry(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'item_id' => 'required|string',
            'entry_id' => 'required|string'
        ]);

        $success = $matricule->removePeriodicKmHistoryEntry(
            $validated['item_id'],
            $validated['entry_id']
        );

        if (!$success) {
            return response()->json([
                'message' => 'History entry not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Periodic kilometer history entry removed successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * NEW: Update periodic kilometer maintenance item
     */
    public function updatePeriodicKmItem(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'item_id' => 'required|string',
            'name' => 'sometimes|string|max:255',
            'interval_km' => 'sometimes|numeric|min:1',
            'notes' => 'nullable|string',
            'required_for_vidange' => 'boolean',
            'needs_attention' => 'boolean'
        ]);

        $success = $matricule->updatePeriodicKmItem($validated['item_id'], $validated);

        if (!$success) {
            return response()->json([
                'message' => 'Periodic kilometer maintenance item not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Periodic kilometer maintenance item updated successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * NEW: Remove periodic kilometer maintenance item
     */
    public function removePeriodicKmItem(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'item_id' => 'required|string'
        ]);

        $matricule->removePeriodicKmItem($validated['item_id']);

        return response()->json([
            'message' => 'Periodic kilometer maintenance item removed successfully',
            'matricule' => $matricule->load('car')
        ]);
    }

    /**
     * UPDATED: Add maintenance item to handle all types
     */
    public function addMaintenanceItem(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:quantity,note,periodic_km',
            'value' => 'required_if:type,quantity',
            'interval_km' => 'required_if:type,periodic_km|numeric|min:1',
            'notes' => 'nullable|string',
            'required_for_vidange' => 'boolean',
            'needs_attention' => 'boolean',
            'due_date' => 'nullable|date'
        ]);

        switch ($validated['type']) {
            case 'periodic_km':
                $matricule->addPeriodicKmMaintenance(
                    $validated['name'],
                    $validated['interval_km'],
                    $validated['notes'] ?? '',
                    $validated['required_for_vidange'] ?? false
                );
                break;
                
            case 'quantity':
            case 'note':
            default:
                $matricule->addMaintenanceItem(
                    $validated['name'],
                    $validated['type'],
                    $validated['value'] ?? '',
                    $validated['notes'] ?? '',
                    $validated['required_for_vidange'] ?? false,
                    $validated['needs_attention'] ?? false,
                    $validated['due_date'] ?? null
                );
                break;
        }

        return response()->json([
            'message' => 'Maintenance item added successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * UPDATED: Update maintenance item to handle all types
     */
    public function updateMaintenanceItem(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'item_id' => 'required|string',
            'type' => 'sometimes|in:quantity,note,periodic_km',
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes',
            'interval_km' => 'required_if:type,periodic_km|numeric|min:1',
            'notes' => 'nullable|string',
            'required_for_vidange' => 'boolean',
            'needs_attention' => 'boolean',
            'due_date' => 'nullable|date',
            'last_done_date' => 'nullable|date'
        ]);

        // Check if item is in periodic_km_maintenance
        $periodicMaintenance = $matricule->periodic_km_maintenance ?? [];
        $isPeriodicItem = collect($periodicMaintenance)->contains(function ($item) use ($validated) {
            return $item['id'] === $validated['item_id'];
        });

        if ($isPeriodicItem) {
            $success = $matricule->updatePeriodicKmItem($validated['item_id'], $validated);
        } else {
            $success = $matricule->updateMaintenanceItem($validated['item_id'], $validated);
        }

        if (!$success) {
            return response()->json([
                'message' => 'Maintenance item not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Maintenance item updated successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * UPDATED: Remove maintenance item to handle all types
     */
    public function removeMaintenanceItem(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'item_id' => 'required|string'
        ]);

        // Check if item is in periodic_km_maintenance
        $periodicMaintenance = $matricule->periodic_km_maintenance ?? [];
        $isPeriodicItem = collect($periodicMaintenance)->contains(function ($item) use ($validated) {
            return $item['id'] === $validated['item_id'];
        });

        if ($isPeriodicItem) {
            $matricule->removePeriodicKmItem($validated['item_id']);
        } else {
            $matricule->removeMaintenanceItem($validated['item_id']);
        }

        return response()->json([
            'message' => 'Maintenance item removed successfully',
            'matricule' => $matricule->load('car')
        ]);
    }

    public function completeMaintenance(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'maintenance_type' => 'required|in:ad_blue,technical_visit,vidange,additional',
            'completion_date' => 'sometimes|date',
            'notes' => 'sometimes|string',
            'item_id' => 'required_if:maintenance_type,additional'
        ]);

        if ($validated['maintenance_type'] === 'additional') {
            $matricule->completeAdditionalMaintenance(
                $validated['item_id'],
                $validated['completion_date'] ?? null,
                $validated['notes'] ?? null
            );
        } else {
            $matricule->completeMaintenance(
                $validated['maintenance_type'],
                $validated['completion_date'] ?? null,
                $validated['notes'] ?? null
            );
        }

        return response()->json([
            'message' => 'Maintenance completed successfully',
            'matricule' => $matricule->load('car'),
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    public function getMaintenanceAlerts($id): JsonResponse
    {
        $matricule = Matricule::with('car')->findOrFail($id);
        
        return response()->json([
            'alerts' => $matricule->maintenance_alerts,
            'matricule' => $matricule
        ]);
    }

    public function getAllMaintenanceAlerts(): JsonResponse
    {
        $matricules = Matricule::with('car')->get();
        
        $allAlerts = [];
        $alertCount = 0;
        
        foreach ($matricules as $matricule) {
            $alerts = $matricule->maintenance_alerts;
            if (!empty($alerts)) {
                $allAlerts[] = [
                    'matricule' => $matricule,
                    'alerts' => $alerts
                ];
                $alertCount += count($alerts);
            }
        }
        
        return response()->json([
            'total_alerts' => $alertCount,
            'alerts_by_matricule' => $allAlerts
        ]);
    }

    public function getByCar($carId): JsonResponse
    {
        $matricules = Matricule::where('car_id', $carId)
            ->with('car')
            ->get();
            
        return response()->json(['matricules' => $matricules]);
    }

    public function updateKilometrage(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'kilometrage' => 'required|numeric|min:0',
        ]);

        $matricule->updateKilometrage($validated['kilometrage']);
        
        return response()->json([
            'message' => 'Kilometrage updated successfully',
            'matricule' => $matricule,
            'maintenance_alerts' => $matricule->maintenance_alerts
        ]);
    }

    /**
     * NEW: Add maintenance item with quantity tracking (similar to oil)
     */
    public function addMaintenanceItemWithQuantity(Request $request, $id): JsonResponse
    {
        $matricule = Matricule::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.1',
            'date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        // Check if this item already exists in additional maintenance
        $additionalMaintenance = $matricule->additional_maintenance ?? [];
        
        // Look for existing item with same name and type quantity
        $existingIndex = collect($additionalMaintenance)->search(function ($item) use ($validated) {
            return $item['name'] === $validated['name'] && $item['type'] === 'quantity';
        });

        // Create history entry
        $historyEntry = [
            'id' => uniqid('quantity_'),
            'quantity' => (float) $validated['quantity'],
            'date' => $validated['date'],
            'notes' => $validated['notes'] ?? '',
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $matricule->kilometrage
        ];

        if ($existingIndex !== false) {
            // Update existing item
            $item = $additionalMaintenance[$existingIndex];
            
            // Add to history
            $history = $item['history'] ?? [];
            $history[] = $historyEntry;
            $item['history'] = $history;
            
            // Update total quantity
            $item['value'] = ($item['value'] ?? 0) + (float) $validated['quantity'];
            $item['last_done_date'] = $validated['date'];
            $item['updated_at'] = now()->toDateTimeString();
            
            $additionalMaintenance[$existingIndex] = $item;
        } else {
            // Create new item
            $newItem = [
                'id' => uniqid('maintenance_'),
                'name' => $validated['name'],
                'type' => 'quantity',
                'value' => (float) $validated['quantity'],
                'notes' => $validated['notes'] ?? '',
                'required_for_vidange' => false,
                'needs_attention' => false,
                'history' => [$historyEntry],
                'last_done_date' => $validated['date'],
                'total_quantity' => (float) $validated['quantity'],
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString()
            ];
            
            $additionalMaintenance[] = $newItem;
        }

        $matricule->additional_maintenance = $additionalMaintenance;
        $matricule->save();
        $matricule->updateVidangeStatus();

        return response()->json([
            'message' => 'Maintenance quantity item added successfully',
            'matricule' => $matricule->load('car')
        ]);
    }
}