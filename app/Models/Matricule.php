<?php
// app/Models/Matricule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Matricule extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SOLD = 'sold';

    protected $fillable = [
        'car_id',
        'matricule_code',
        'kilometrage',
        'status',
        'visit_tech',
        'date_taxe_voiture',
        'date_assurance',
        'vidange_status',
        
        // Maintenance fields
        'paquets_de_voiture',
        'paquets_de_voiture_date',
        'paquets_de_voiture_count',
        'paquets_de_voiture_history',
        
        'paquets_de_frein',
        'paquets_de_frein_date',
        'paquets_de_frein_count',
        'paquets_de_frein_history',
        
        'filter_oil',
        'filter_oil_date',
        'filter_oil_count',
        'filter_oil_history',
        
        'filter_air',
        'filter_air_date',
        'filter_air_count',
        'filter_air_history',
        
        'ad_blue',
        'ad_blue_date',
        'ad_blue_quantity',
        'ad_blue_history',
        
        'oil',
        'oil_date',
        'oil_quantity',
        'oil_history',
        
        // Additional maintenance
        'additional_maintenance',
        'periodic_km_maintenance',
        
        // Vidange tracking
        'last_vidange_km',
        'vidange_interval'
    ];

    protected $casts = [
        'visit_tech' => 'date',
        'date_taxe_voiture' => 'date',
        'date_assurance' => 'date',
        'kilometrage' => 'decimal:2',
        'last_vidange_km' => 'decimal:2',
        'vidange_interval' => 'decimal:2',
        'additional_maintenance' => 'array',
        'periodic_km_maintenance' => 'array',
        
        // Maintenance dates
        'paquets_de_voiture_date' => 'date',
        'paquets_de_frein_date' => 'date',
        'filter_oil_date' => 'date',
        'filter_air_date' => 'date',
        'ad_blue_date' => 'date',
        'oil_date' => 'date',
        
        // Quantities and counts
        'oil_quantity' => 'decimal:2',
        'ad_blue_quantity' => 'decimal:2',
        'filter_oil_count' => 'integer',
        'filter_air_count' => 'integer',
        'paquets_de_frein_count' => 'integer',
        'paquets_de_voiture_count' => 'integer',
        
        // History arrays
        'oil_history' => 'array',
        'ad_blue_history' => 'array',
        'filter_oil_history' => 'array',
        'filter_air_history' => 'array',
        'paquets_de_frein_history' => 'array',
        'paquets_de_voiture_history' => 'array'
    ];

    // Relationships
    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function accidents()
    {
        return $this->hasMany(Accident::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope to exclude sold matricules.
     * Use this when populating dropdowns for reservations, accidents, or financings.
     */
    public function scopeNotSold($query)
    {
        return $query->where('status', '!=', self::STATUS_SOLD);
    }

    // Attribute getters
    public function getIsVisitTechExpiredAttribute()
    {
        return $this->visit_tech && $this->visit_tech->lt(now());
    }

    public function getIsTaxeVoitureExpiredAttribute()
    {
        return $this->date_taxe_voiture && $this->date_taxe_voiture->lt(now());
    }

    public function getIsAssuranceExpiredAttribute()
    {
        return $this->date_assurance && $this->date_assurance->lt(now());
    }

    public function getIsVisitTechExpiringSoonAttribute()
    {
        return $this->visit_tech && $this->visit_tech->lt(now()->addDays(30));
    }

    public function getIsTaxeVoitureExpiringSoonAttribute()
    {
        return $this->date_taxe_voiture && $this->date_taxe_voiture->lt(now()->addDays(30));
    }

    public function getIsAssuranceExpiringSoonAttribute()
    {
        return $this->date_assurance && $this->date_assurance->lt(now()->addDays(30));
    }

    public function getIsVidangeNeededAttribute()
    {
        if (!$this->last_vidange_km || !$this->vidange_interval) {
            return false;
        }
        
        $kmSinceLastVidange = $this->kilometrage - $this->last_vidange_km;
        return $kmSinceLastVidange >= $this->vidange_interval;
    }

    // NEW: Maintenance quantity methods
    public function addOilChange($quantity, $date = null)
    {
        $history = $this->oil_history ?? [];
        
        $entry = [
            'id' => uniqid('oil_'),
            'quantity' => (float) $quantity,
            'date' => $date ?: now()->toDateString(),
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $this->kilometrage
        ];
        
        $history[] = $entry;
        $this->oil_history = $history;
        
        // Update total quantity
        $this->oil_quantity = ($this->oil_quantity ?? 0) + (float) $quantity;
        
        // Mark oil as changed
        $this->oil = 'yes';
        $this->oil_date = $date ?: now()->toDateString();
        
        $this->save();
        $this->updateVidangeStatus();
        
        return $this;
    }

    public function addAdBlue($quantity, $date = null)
    {
        $history = $this->ad_blue_history ?? [];
        
        $entry = [
            'id' => uniqid('ad_blue_'),
            'quantity' => (float) $quantity,
            'date' => $date ?: now()->toDateString(),
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $this->kilometrage
        ];
        
        $history[] = $entry;
        $this->ad_blue_history = $history;
        
        // Update total quantity
        $this->ad_blue_quantity = ($this->ad_blue_quantity ?? 0) + (float) $quantity;
        
        // Mark Ad Blue as refilled
        $this->ad_blue = 'yes';
        $this->ad_blue_date = $date ?: now()->toDateString();
        
        $this->save();
        return $this;
    }

    public function addFilterOilChange($date = null)
    {
        $history = $this->filter_oil_history ?? [];
        
        $entry = [
            'id' => uniqid('filter_oil_'),
            'date' => $date ?: now()->toDateString(),
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $this->kilometrage
        ];
        
        $history[] = $entry;
        $this->filter_oil_history = $history;
        
        // Update count
        $this->filter_oil_count = ($this->filter_oil_count ?? 0) + 1;
        
        // Mark filter oil as changed
        $this->filter_oil = 'yes';
        $this->filter_oil_date = $date ?: now()->toDateString();
        
        $this->save();
        $this->updateVidangeStatus();
        
        return $this;
    }

    public function addFilterAirChange($date = null)
    {
        $history = $this->filter_air_history ?? [];
        
        $entry = [
            'id' => uniqid('filter_air_'),
            'date' => $date ?: now()->toDateString(),
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $this->kilometrage
        ];
        
        $history[] = $entry;
        $this->filter_air_history = $history;
        
        // Update count
        $this->filter_air_count = ($this->filter_air_count ?? 0) + 1;
        
        // Mark filter air as changed
        $this->filter_air = 'yes';
        $this->filter_air_date = $date ?: now()->toDateString();
        
        $this->save();
        return $this;
    }

    public function addBrakePadsChange($date = null)
    {
        $history = $this->paquets_de_frein_history ?? [];
        
        $entry = [
            'id' => uniqid('brake_pads_'),
            'date' => $date ?: now()->toDateString(),
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $this->kilometrage
        ];
        
        $history[] = $entry;
        $this->paquets_de_frein_history = $history;
        
        // Update count
        $this->paquets_de_frein_count = ($this->paquets_de_frein_count ?? 0) + 1;
        
        // Mark brake pads as changed
        $this->paquets_de_frein = 'yes';
        $this->paquets_de_frein_date = $date ?: now()->toDateString();
        
        $this->save();
        return $this;
    }

    public function addCarPadsChange($date = null)
    {
        $history = $this->paquets_de_voiture_history ?? [];
        
        $entry = [
            'id' => uniqid('car_pads_'),
            'date' => $date ?: now()->toDateString(),
            'created_at' => now()->toDateTimeString(),
            'kilometrage' => $this->kilometrage
        ];
        
        $history[] = $entry;
        $this->paquets_de_voiture_history = $history;
        
        // Update count
        $this->paquets_de_voiture_count = ($this->paquets_de_voiture_count ?? 0) + 1;
        
        // Mark car pads as changed
        $this->paquets_de_voiture = 'yes';
        $this->paquets_de_voiture_date = $date ?: now()->toDateString();
        
        $this->save();
        return $this;
    }

    // Get history methods
    public function getOilHistory()
    {
        return collect($this->oil_history ?? [])->sortByDesc('date')->values();
    }

    public function getAdBlueHistory()
    {
        return collect($this->ad_blue_history ?? [])->sortByDesc('date')->values();
    }

    public function getFilterOilHistory()
    {
        return collect($this->filter_oil_history ?? [])->sortByDesc('date')->values();
    }

    public function getFilterAirHistory()
    {
        return collect($this->filter_air_history ?? [])->sortByDesc('date')->values();
    }

    public function getBrakePadsHistory()
    {
        return collect($this->paquets_de_frein_history ?? [])->sortByDesc('date')->values();
    }

    public function getCarPadsHistory()
    {
        return collect($this->paquets_de_voiture_history ?? [])->sortByDesc('date')->values();
    }

    // Remove history entry
    public function removeMaintenanceHistoryEntry($type, $entryId)
    {
        $historyField = $type . '_history';
        $quantityField = $type . '_quantity';
        $countField = $type . '_count';
        
        if (!isset($this->$historyField)) {
            return false;
        }
        
        $history = collect($this->$historyField);
        $entry = $history->firstWhere('id', $entryId);
        
        if (!$entry) {
            return false;
        }
        
        // For quantity-based items (oil, ad_blue)
        if (in_array($type, ['oil', 'ad_blue']) && isset($entry['quantity'])) {
            $this->$quantityField = max(0, ($this->$quantityField ?? 0) - $entry['quantity']);
            
            // If quantity reaches 0, mark as not done
            if ($this->$quantityField <= 0) {
                $this->$type = 'no';
                $this->{$type . '_date'} = null;
            }
        }
        
        // For count-based items
        if (in_array($type, ['filter_oil', 'filter_air', 'paquets_de_frein', 'paquets_de_voiture'])) {
            $this->$countField = max(0, ($this->$countField ?? 0) - 1);
            
            // If count reaches 0, mark as not done
            if ($this->$countField <= 0) {
                $this->$type = 'no';
                $this->{$type . '_date'} = null;
            }
        }
        
        // Remove the entry
        $filteredHistory = $history->filter(function ($item) use ($entryId) {
            return $item['id'] !== $entryId;
        })->values()->toArray();
        
        $this->$historyField = $filteredHistory;
        $this->save();
        
        // Update vidange status if oil or filter oil changed
        if (in_array($type, ['oil', 'filter_oil'])) {
            $this->updateVidangeStatus();
        }
        
        return true;
    }

    /**
     * NEW: Add a periodic kilometer-based maintenance item
     */
    public function addPeriodicKmMaintenance($name, $intervalKm, $notes = '', $required_for_vidange = false)
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        $newItem = [
            'id' => uniqid('periodic_km_'),
            'name' => $name,
            'type' => 'periodic_km',
            'interval_km' => (float) $intervalKm,
            'last_changed_km' => $this->kilometrage,
            'next_change_km' => $this->kilometrage + (float) $intervalKm,
            'notes' => $notes,
            'required_for_vidange' => $required_for_vidange,
            'needs_attention' => false, // Starts as not needing attention
            'change_history' => [],
            'change_count' => 0,
            'last_changed_date' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString()
        ];

        $periodicMaintenance[] = $newItem;
        $this->periodic_km_maintenance = $periodicMaintenance;
        
        $this->save();
        $this->updateVidangeStatus();
        
        return $this;
    }

    /**
     * NEW: Add a change to periodic kilometer maintenance
     */
    public function addPeriodicKmChange($itemId, $date = null)
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        $itemIndex = collect($periodicMaintenance)->search(function ($item) use ($itemId) {
            return $item['id'] === $itemId;
        });

        if ($itemIndex !== false) {
            $item = $periodicMaintenance[$itemIndex];
            
            // Create history entry
            $historyEntry = [
                'id' => uniqid('change_'),
                'date' => $date ?: now()->toDateString(),
                'changed_km' => $this->kilometrage,
                'created_at' => now()->toDateTimeString()
            ];

            // Add to history
            $history = $item['change_history'] ?? [];
            $history[] = $historyEntry;
            
            // Update item
            $item['change_history'] = $history;
            $item['change_count'] = ($item['change_count'] ?? 0) + 1;
            $item['last_changed_km'] = $this->kilometrage;
            $item['next_change_km'] = $this->kilometrage + $item['interval_km'];
            $item['last_changed_date'] = $date ?: now()->toDateString();
            $item['needs_attention'] = false; // Reset needs attention when changed
            $item['updated_at'] = now()->toDateTimeString();
            
            $periodicMaintenance[$itemIndex] = $item;
            $this->periodic_km_maintenance = $periodicMaintenance;
            
            $this->save();
            $this->updateVidangeStatus();
            
            return true;
        }
        
        return false;
    }

    /**
     * NEW: Get periodic kilometer maintenance history
     */
    public function getPeriodicKmHistory($itemId)
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        $item = collect($periodicMaintenance)->firstWhere('id', $itemId);
        
        if (!$item) {
            return collect();
        }
        
        return collect($item['change_history'] ?? [])->sortByDesc('date')->values();
    }

    /**
     * NEW: Remove a history entry from periodic kilometer maintenance
     */
    public function removePeriodicKmHistoryEntry($itemId, $entryId)
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        $itemIndex = collect($periodicMaintenance)->search(function ($item) use ($itemId) {
            return $item['id'] === $itemId;
        });

        if ($itemIndex !== false) {
            $item = $periodicMaintenance[$itemIndex];
            $history = collect($item['change_history'] ?? []);
            
            $entry = $history->firstWhere('id', $entryId);
            
            if (!$entry) {
                return false;
            }
            
            // Remove the entry
            $filteredHistory = $history->filter(function ($historyItem) use ($entryId) {
                return $historyItem['id'] !== $entryId;
            })->values()->toArray();
            
            // Update item
            $item['change_history'] = $filteredHistory;
            $item['change_count'] = max(0, ($item['change_count'] ?? 0) - 1);
            
            // If this was the last change, update last_changed_km and next_change_km
            if (empty($filteredHistory)) {
                $item['last_changed_km'] = 0;
                $item['next_change_km'] = $item['interval_km'];
                $item['last_changed_date'] = null;
                $item['needs_attention'] = $this->kilometrage >= $item['interval_km'];
            } else {
                // Get the most recent change
                $recentChange = collect($filteredHistory)->sortByDesc('date')->first();
                $item['last_changed_km'] = $recentChange['changed_km'] ?? 0;
                $item['next_change_km'] = ($recentChange['changed_km'] ?? 0) + $item['interval_km'];
                $item['last_changed_date'] = $recentChange ? $recentChange['date'] : null;
                $item['needs_attention'] = $this->kilometrage >= ($item['next_change_km']);
            }
            
            $periodicMaintenance[$itemIndex] = $item;
            $this->periodic_km_maintenance = $periodicMaintenance;
            
            $this->save();
            $this->updateVidangeStatus();
            
            return true;
        }
        
        return false;
    }

    /**
     * NEW: Update periodic kilometer maintenance item
     */
    public function updatePeriodicKmItem($itemId, $data)
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        $itemIndex = collect($periodicMaintenance)->search(function ($item) use ($itemId) {
            return $item['id'] === $itemId;
        });

        if ($itemIndex !== false) {
            $item = $periodicMaintenance[$itemIndex];
            
            // Handle interval_km change - recalculate next_change_km
            if (isset($data['interval_km'])) {
                $item['interval_km'] = (float) $data['interval_km'];
                $item['next_change_km'] = $item['last_changed_km'] + $item['interval_km'];
            }
            
            // Update other fields
            if (isset($data['name'])) $item['name'] = $data['name'];
            if (isset($data['notes'])) $item['notes'] = $data['notes'];
            if (isset($data['required_for_vidange'])) $item['required_for_vidange'] = $data['required_for_vidange'];
            if (isset($data['needs_attention'])) $item['needs_attention'] = $data['needs_attention'];
            
            $item['updated_at'] = now()->toDateTimeString();
            
            $periodicMaintenance[$itemIndex] = $item;
            $this->periodic_km_maintenance = $periodicMaintenance;
            
            $this->save();
            $this->updateVidangeStatus();
            
            return true;
        }
        
        return false;
    }

    /**
     * NEW: Remove periodic kilometer maintenance item
     */
    public function removePeriodicKmItem($itemId)
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        $filteredMaintenance = array_filter($periodicMaintenance, function ($item) use ($itemId) {
            return $item['id'] !== $itemId;
        });

        $this->periodic_km_maintenance = array_values($filteredMaintenance);
        $this->save();
        $this->updateVidangeStatus();
        
        return $this;
    }

    /**
     * Check if periodic kilometer maintenance needs attention
     */
    public function getPeriodicKmNeedsAttentionAttribute()
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        
        foreach ($periodicMaintenance as $item) {
            if (isset($item['next_change_km']) && $this->kilometrage >= $item['next_change_km']) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all periodic kilometer maintenance items that need attention
     */
    public function getPeriodicKmAlertsAttribute()
    {
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        $alerts = [];
        
        foreach ($periodicMaintenance as $item) {
            if (isset($item['next_change_km']) && $this->kilometrage >= $item['next_change_km']) {
                $kmOverdue = $this->kilometrage - $item['next_change_km'];
                $alerts[] = [
                    'item' => $item,
                    'km_overdue' => $kmOverdue,
                    'message' => "{$item['name']} needs attention (overdue by {$kmOverdue} km)"
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Update all maintenance items when kilometrage changes
     */
    public function updateMaintenanceStatusOnKilometrageChange()
    {
        // Update periodic km maintenance status
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        $updated = false;
        
        foreach ($periodicMaintenance as $index => $item) {
            if (isset($item['next_change_km']) && $this->kilometrage >= $item['next_change_km']) {
                if (!$item['needs_attention']) {
                    $item['needs_attention'] = true;
                    $periodicMaintenance[$index] = $item;
                    $updated = true;
                }
            }
        }
        
        if ($updated) {
            $this->periodic_km_maintenance = $periodicMaintenance;
        }
        
        return $this;
    }

    public function updateVidangeStatus()
    {
        // UPDATED: Vidange now depends ONLY on oil and filter_oil
        $requiredMaintenanceDone = 
            $this->oil === 'yes' &&
            $this->filter_oil === 'yes';

        // Check additional maintenance items that are required_for_vidange
        $additionalMaintenance = $this->additional_maintenance ?? [];
        $allAdditionalRequiredDone = true;
        
        foreach ($additionalMaintenance as $item) {
            if (isset($item['required_for_vidange']) && $item['required_for_vidange']) {
                if ($item['type'] === 'quantity') {
                    // For quantity items, check if value is > 0
                    if (!isset($item['value']) || (float)$item['value'] <= 0) {
                        $allAdditionalRequiredDone = false;
                        break;
                    }
                } else if ($item['type'] === 'note') {
                    // For note items, check if needs_attention is false
                    if (isset($item['needs_attention']) && $item['needs_attention']) {
                        $allAdditionalRequiredDone = false;
                        break;
                    }
                }
                // periodic_km items are handled separately
            }
        }

        // Check periodic km maintenance items that are required_for_vidange
        $periodicMaintenance = $this->periodic_km_maintenance ?? [];
        $allPeriodicRequiredDone = true;
        
        foreach ($periodicMaintenance as $item) {
            if (isset($item['required_for_vidange']) && $item['required_for_vidange']) {
                if (isset($item['needs_attention']) && $item['needs_attention']) {
                    $allPeriodicRequiredDone = false;
                    break;
                }
            }
        }

        if ($requiredMaintenanceDone && $allAdditionalRequiredDone && $allPeriodicRequiredDone) {
            $this->vidange_status = 'done';
            if ($this->last_vidange_km != $this->kilometrage) {
                $this->last_vidange_km = $this->kilometrage;
            }
        } else {
            $this->vidange_status = 'not done';
        }

        return $this->save();
    }

    public function addMaintenanceItem($name, $type = 'note', $value = null, $notes = '', $required_for_vidange = false, $needs_attention = false, $due_date = null)
    {
        $additionalMaintenance = $this->additional_maintenance ?? [];
        
        $newItem = [
            'id' => uniqid('maintenance_'),
            'name' => $name,
            'type' => $type,
            'value' => $type === 'quantity' ? (float) $value : $value,
            'notes' => $notes,
            'required_for_vidange' => $required_for_vidange,
            'needs_attention' => $needs_attention,
            'due_date' => $due_date,
            'last_done_date' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString()
        ];

        // For quantity type, add history tracking
        if ($type === 'quantity' && $value > 0) {
            $newItem['history'] = [[
                'id' => uniqid('quantity_'),
                'quantity' => (float) $value,
                'date' => now()->toDateString(),
                'created_at' => now()->toDateTimeString(),
                'kilometrage' => $this->kilometrage
            ]];
            $newItem['total_quantity'] = (float) $value;
            $newItem['last_done_date'] = now()->toDateString();
        }

        $additionalMaintenance[] = $newItem;
        $this->additional_maintenance = $additionalMaintenance;
        return $this;
    }

    public function updateMaintenanceItem($itemId, $data)
    {
        $additionalMaintenance = $this->additional_maintenance ?? [];
        
        $itemIndex = collect($additionalMaintenance)->search(function ($item) use ($itemId) {
            return $item['id'] === $itemId;
        });

        if ($itemIndex !== false) {
            $additionalMaintenance[$itemIndex] = array_merge(
                $additionalMaintenance[$itemIndex],
                $data,
                ['updated_at' => now()->toDateTimeString()]
            );
            
            $this->additional_maintenance = $additionalMaintenance;
            return true;
        }
        
        return false;
    }

    public function removeMaintenanceItem($itemId)
    {
        $additionalMaintenance = $this->additional_maintenance ?? [];
        
        $updatedMaintenance = array_filter($additionalMaintenance, function ($item) use ($itemId) {
            return $item['id'] !== $itemId;
        });

        $this->additional_maintenance = array_values($updatedMaintenance);
        return $this;
    }

    public function getMaintenanceItemTotal($itemName)
    {
        $additionalMaintenance = $this->additional_maintenance ?? [];
        
        $item = collect($additionalMaintenance)->firstWhere('name', $itemName);
        
        return $item && $item['type'] === 'quantity' ? $item['value'] : 0;
    }

    public function updateKilometrage($newKilometrage)
    {
        $oldKilometrage = $this->kilometrage;
        $this->kilometrage = $newKilometrage;
        
        if ($newKilometrage - $oldKilometrage >= 10000) {
            $this->vidange_status = 'not done';
            // UPDATED: Reset ONLY oil and filter_oil (required for vidange)
            $this->oil = 'no';
            $this->filter_oil = 'no';
            // Optional items remain as they are
        }
        
        // Update periodic maintenance status
        $this->updateMaintenanceStatusOnKilometrageChange();
        
        if ($this->is_vidange_needed) {
            $this->vidange_status = 'not done';
        }
        
        $this->save();
        $this->updateVidangeStatus();
        
        return $this;
    }

    public function scopeWithPaquetsDeVoiture($query, $value)
    {
        return $query->where('paquets_de_voiture', $value);
    }

    public function scopeWithPaquetsDeFrein($query, $value)
    {
        return $query->where('paquets_de_frein', $value);
    }

    public function scopeWithFilterOil($query, $value)
    {
        return $query->where('filter_oil', $value);
    }

    public function scopeWithFilterAir($query, $value)
    {
        return $query->where('filter_air', $value);
    }

    public function scopeWithAdBlue($query, $value)
    {
        return $query->where('ad_blue', $value);
    }

    public function scopeWithOil($query, $value)
    {
        return $query->where('oil', $value);
    }

    /**
     * Get all maintenance alerts including periodic km
     */
    public function getMaintenanceAlertsAttribute()
    {
        $alerts = [];
        
        // Existing alerts...
        if ($this->is_visit_tech_expiring_soon) {
            $alerts[] = [
                'type' => 'visit_tech',
                'message' => 'Visite technique expire bientôt',
                'severity' => 'medium',
                'date' => $this->visit_tech->toDateString()
            ];
        }
        
        if ($this->is_taxe_voiture_expiring_soon) {
            $alerts[] = [
                'type' => 'taxe_voiture',
                'message' => 'Taxe voiture expire bientôt',
                'severity' => 'medium',
                'date' => $this->date_taxe_voiture->toDateString()
            ];
        }
        
        if ($this->is_assurance_expiring_soon) {
            $alerts[] = [
                'type' => 'assurance',
                'message' => 'Assurance expire bientôt',
                'severity' => 'medium',
                'date' => $this->date_assurance->toDateString()
            ];
        }
        
        if ($this->is_vidange_needed) {
            $alerts[] = [
                'type' => 'vidange',
                'message' => 'Vidange nécessaire',
                'severity' => 'high',
                'date' => now()->toDateString()
            ];
        }
        
        // Add periodic km alerts
        $periodicAlerts = $this->periodic_km_alerts;
        foreach ($periodicAlerts as $alert) {
            $alerts[] = [
                'type' => 'periodic_km',
                'item' => $alert['item']['name'],
                'message' => $alert['message'],
                'severity' => 'medium',
                'date' => now()->toDateString()
            ];
        }
        
        return $alerts;
    }
}