<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockMovement extends Model
{
    protected $fillable = [
        'product_id', 'type', 'quantity', 'stock_after', 'volume',
        'reason', 'product_sale_id', 'lavage_id', 'created_by',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'stock_after' => 'integer',
        'volume'      => 'float',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function sale()    { return $this->belongsTo(ProductSale::class, 'product_sale_id'); }
    public function lavage()  { return $this->belongsTo(Lavage::class); }
}