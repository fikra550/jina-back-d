<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LavageProduct extends Model
{
    protected $fillable = [
        'lavage_id', 'product_id', 'quantity',
        'unit_cost', 'total_cost', 'created_by',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_cost'  => 'float',
        'total_cost' => 'float',
    ];

    public function lavage()  { return $this->belongsTo(Lavage::class); }
    public function product() { return $this->belongsTo(Product::class); }
}