<?php
// app/Models/CarFinancing.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarFinancing extends Model
{
    use HasFactory;

    protected $table = 'car_financings';

    protected $fillable = [
        'matricule_id',
        'dossier_number',
        'account_number',
        'credit_type',
        'contract_date',
        'credit_amount',
        'preti_interet_ttc_differe',
        'duration_months',
        'differed_months',
        'periodicity',
        'total_installments',
        'first_installment_date',
        'last_installment_date',
        'bank_name',
        'bank_account',
        'current_installment_number',
        'total_paid',
        'total_remaining',
        'status',
        'installment_amount',
        'prestation_amount',
        'amortization_schedule',
        'notes'
    ];

    protected $casts = [
        'contract_date' => 'date',
        'first_installment_date' => 'date',
        'last_installment_date' => 'date',
        'credit_amount' => 'decimal:2',
        'preti_interet_ttc_differe' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'total_remaining' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'prestation_amount' => 'decimal:2',
        'amortization_schedule' => 'array'
    ];

    // Relationships
    public function matricule()
    {
        return $this->belongsTo(Matricule::class);
    }

    public function payments()
    {
        return $this->hasMany(CarFinancingPayment::class, 'car_financing_id');
    }

    // Helper methods
    public function getProgressPercentageAttribute()
    {
        if ($this->total_installments == 0) {
            return 0;
        }
        return ($this->current_installment_number / $this->total_installments) * 100;
    }

    public function getRemainingMonthsAttribute()
    {
        return $this->total_installments - $this->current_installment_number;
    }

    public function getNextPaymentDueAttribute()
    {
        $nextPayment = $this->payments()
            ->where('status', 'pending')
            ->orderBy('installment_number')
            ->first();
            
        return $nextPayment ? $nextPayment->due_date : null;
    }

    public function isOverdueAttribute()
    {
        $nextPayment = $this->getNextPaymentDueAttribute();
        if (!$nextPayment) {
            return false;
        }
        return $nextPayment < now() && $this->status === 'active';
    }
}