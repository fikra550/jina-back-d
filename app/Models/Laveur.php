<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Laveur extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'pay_type',   // percentage | fixed
        'pay_rate',   // % du prix  OU  montant fixe par lavage
        'is_active',
        'notes',
    ];

    protected $casts = [
        'pay_rate'  => 'float',
        'is_active' => 'boolean',
    ];

    public function lavages(): HasMany
    {
        return $this->hasMany(Lavage::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LaveurPayment::class);
    }

    /**
     * Ce que le laveur gagne sur un lavage de $price DH.
     */
    public function computeAmount(float $price): float
    {
        $amount = $this->pay_type === 'fixed'
            ? (float) $this->pay_rate
            : $price * ((float) $this->pay_rate) / 100;

        return round($amount, 2);
    }
}