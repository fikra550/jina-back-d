<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatriculeProduct extends Model
{
    protected $fillable = [
        'matricule_id', 'product_id', 'quantity', 'volume_used',
        'unit_cost', 'total_cost', 'purpose', 'notes', 'added_by',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'volume_used' => 'float',
        'unit_cost'   => 'decimal:2',
        'total_cost'  => 'decimal:2',
    ];

    public function matricule()
    {
        return $this->belongsTo(Matricule::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}