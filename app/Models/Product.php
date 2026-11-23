<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'reference', 'description', 'category_id', 'unit',
        'purchase_price', 'selling_price', 'stock_quantity',
        'volume_per_unit', 'open_volume',
        'alert_threshold', 'image', 'is_active',
    ];

    protected $casts = [
        'purchase_price'  => 'float',
        'selling_price'   => 'float',
        'stock_quantity'  => 'integer',
        'volume_per_unit' => 'float',
        'open_volume'     => 'float',
        'alert_threshold' => 'integer',
        'is_active'       => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($p) {
            if ($p->unit === null)            $p->unit            = 'unité';
            if ($p->purchase_price === null)  $p->purchase_price  = 0;
            if ($p->selling_price === null)   $p->selling_price   = 0;
            if ($p->stock_quantity === null)  $p->stock_quantity  = 0;
            if ($p->open_volume === null)     $p->open_volume     = 0;
            if ($p->alert_threshold === null) $p->alert_threshold = 5;
            if ($p->is_active === null)       $p->is_active       = true;
        });
    }

    public function category()       { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function sales()          { return $this->hasMany(ProductSale::class); }
    public function stockMovements() { return $this->hasMany(ProductStockMovement::class); }

    public function scopeActive($q)  { return $q->where('is_active', true); }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->alert_threshold;
    }
}