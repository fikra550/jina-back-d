<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSale extends Model
{
    protected $fillable = [
        'product_id', 'client_id', 'car_id', 'matricule_id',
        'quantity', 'unit_price', 'total_price',
        'payment_status', 'amount_paid', 'notes', 'sale_date', 'created_by',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'unit_price'  => 'float',
        'total_price' => 'float',
        'amount_paid' => 'float',
        'sale_date'   => 'date',
    ];

    public function product()   { return $this->belongsTo(Product::class); }
    public function client()    { return $this->belongsTo(Client::class); }
    public function car()       { return $this->belongsTo(Car::class); }
    public function matricule() { return $this->belongsTo(Matricule::class); }
}