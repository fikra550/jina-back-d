<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SousLocation extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];
    protected $attributes = [
    'status' => 'active',
];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}