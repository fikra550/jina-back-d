<?php
// app/Models/CarFinancingPayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarFinancingPayment extends Model
{
    use HasFactory;

    protected $table = 'car_financing_payments';

    protected $fillable = [
        'car_financing_id',
        'due_date',
        'installment_number',
        'capital_amount',
        'interest_amount',
        'tva_amount',
        'prestation_amount',
        'total_amount',
        'status',
        'paid_date',
        'paid_amount',
        'remaining_capital',
        'remaining_interest',
        'remaining_total',
        'payment_method',
        'transaction_reference',
        'payment_notes'
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'capital_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'tva_amount' => 'decimal:2',
        'prestation_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_capital' => 'decimal:2',
        'remaining_interest' => 'decimal:2',
        'remaining_total' => 'decimal:2'
    ];

    // Relationships
    public function financing()
    {
        return $this->belongsTo(CarFinancing::class, 'car_financing_id');
    }

    // Helper methods
    public function getIsOverdueAttribute()
    {
        return $this->status === 'pending' && $this->due_date < now();
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->getIsOverdueAttribute()) {
            return 0;
        }
        return now()->diffInDays($this->due_date);
    }
}