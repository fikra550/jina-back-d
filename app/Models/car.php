<?php
// app/Models/Car.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'model',
        'year',
        'color',
        'image',
        'price_per_day',
        'status',
        'seats',
        'doors',
        'fuel_type',
        'transmission',
    ];

    protected $casts = [
        'price_per_day' => 'decimal:2',
        'year' => 'integer',
    ];

    // Accessor for image URL
    public function getImageUrlAttribute()
    {
        if ($this->image && Storage::disk('public')->exists($this->image)) {
            return Storage::disk('public')->url($this->image);
        }
        return null;
    }

    // Append image_url to JSON
    protected $appends = ['image_url'];

    // Relationships
    public function matricules()
    {
        return $this->hasMany(Matricule::class);
    }

    public function currentMatricule()
    {
        return $this->hasOne(Matricule::class)->where('status', 'active')->latest();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function accidents()
    {
        return $this->hasMany(Accident::class);
    }

    // Helper method to get active matricule or the latest one
    public function getActiveMatriculeAttribute()
    {
        return $this->currentMatricule ?? $this->matricules()->latest()->first();
    }

    /**
     * Update car status based on matricules
     * Returns the new status
     */
    public function updateStatusBasedOnMatricules()
    {
        $hasActiveMatricules = $this->matricules()
            ->where('status', 'active')
            ->exists();
        
        $newStatus = $hasActiveMatricules ? 'disponible' : 'non disponible';
        
        // Only update if status has changed
        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
        
        return $newStatus;
    }

    /**
     * Get real-time status based on matricules
     * This ensures status is always accurate even if not synced in DB
     */
    public function getRealTimeStatusAttribute()
    {
        $hasActiveMatricules = $this->matricules()
            ->where('status', 'active')
            ->exists();
        
        return $hasActiveMatricules ? 'disponible' : 'non disponible';
    }

    /**
     * Scope for available cars (has active matricules)
     */
    public function scopeAvailable($query)
    {
        return $query->whereHas('matricules', function($q) {
            $q->where('status', 'active');
        });
    }

    /**
     * Scope for unavailable cars (no active matricules)
     */
    public function scopeUnavailable($query)
    {
        return $query->whereDoesntHave('matricules', function($q) {
            $q->where('status', 'active');
        });
    }
}