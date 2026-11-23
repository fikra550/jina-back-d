<?php
// app/Models/Client.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'email',
        'city',
        'cin_number',
        'driver_license_number',
        'cin_image',
        'driver_license_image',
        'date_naissance',
        'lieu_naissance',
        'cin_delivre_le',
        'permis_delivre_le'
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'cin_delivre_le' => 'date',
        'permis_delivre_le' => 'date',
    ];

    // Accessors for file URLs
    public function getCinImageUrlAttribute()
    {
        if ($this->cin_image && Storage::disk('public')->exists($this->cin_image)) {
            return Storage::disk('public')->url($this->cin_image);
        }
        return null;
    }

    public function getDriverLicenseImageUrlAttribute()
    {
        if ($this->driver_license_image && Storage::disk('public')->exists($this->driver_license_image)) {
            return Storage::disk('public')->url($this->driver_license_image);
        }
        return null;
    }

    // Check if file is PDF
    public function getCinIsPdfAttribute()
    {
        if (!$this->cin_image) return false;
        return pathinfo($this->cin_image, PATHINFO_EXTENSION) === 'pdf';
    }

    public function getDriverLicenseIsPdfAttribute()
    {
        if (!$this->driver_license_image) return false;
        return pathinfo($this->driver_license_image, PATHINFO_EXTENSION) === 'pdf';
    }

    // Append file URLs and PDF checks to JSON
    protected $appends = [
        'cin_image_url', 
        'driver_license_image_url',
        'cin_is_pdf',
        'driver_license_is_pdf'
    ];

    // Relationships
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    // Get full name
    public function getFullNameAttribute()
    {
        return $this->prenom . ' ' . $this->nom;
    }

    // Format date for display
    public function getDateNaissanceFormattedAttribute()
    {
        return $this->date_naissance ? $this->date_naissance->format('d/m/Y') : null;
    }

    public function getCinDelivreLeFormattedAttribute()
    {
        return $this->cin_delivre_le ? $this->cin_delivre_le->format('d/m/Y') : null;
    }

    public function getPermisDelivreLeFormattedAttribute()
    {
        return $this->permis_delivre_le ? $this->permis_delivre_le->format('d/m/Y') : null;
    }
}