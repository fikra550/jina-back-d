<?php
// app/Models/Garage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'rc',
        'if',
        'ice',
        'tp',
        'notes',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function accidents()
    {
        return $this->hasMany(Accident::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getFullAddressAttribute()
    {
        return $this->address . ($this->phone ? ' - Tél: ' . $this->phone : '');
    }
}