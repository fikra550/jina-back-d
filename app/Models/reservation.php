<?php
// app/Models/Reservation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'total_days',
        'rental_days',
        'total_price',
        'amount_paid',
        'remaining_amount',
        'payment_history',
        'status',
        'matricule_id',
        'car_id',
        'client_id',
        'has_second_driver',
        'second_driver_client_id',
        'notes',
        'reception_notes',
        'kilometrage_sortie',
        'kilometrage_entree',
        'matricule_kilometrage_at_start',
        'matricule_kilometrage_at_end',
        'signatures',
        'signature_code',
        'signature_token',
        'signature_expires_at',
        'created_by',
        // NEW contract number fields
        'contract_number',
        'contract_year',
        'sous_location_id',
        'can_extend_days',
        'prolongation_days',
        'signature_token_second_driver',
        'signature_code_second_driver',
        'signature_expires_at_second_driver',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'total_price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'payment_history' => 'array',
        'has_second_driver' => 'boolean',
        'kilometrage_sortie' => 'decimal:2',
        'kilometrage_entree' => 'decimal:2',
        'matricule_kilometrage_at_start' => 'decimal:2',
        'matricule_kilometrage_at_end' => 'decimal:2',
        'signatures' => 'array',
        'signature_code' => 'string',
        'signature_token' => 'string',
        'signature_expires_at' => 'datetime',
        'contract_number' => 'integer',
        'contract_year' => 'integer',
        'can_extend_days' => 'boolean',
        'prolongation_days' => 'integer',
        'signature_expires_at_second_driver' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================
    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function secondDriverClient()
    {
        return $this->belongsTo(Client::class, 'second_driver_client_id');
    }

    public function matricule()
    {
        return $this->belongsTo(Matricule::class);
    }

    // ==================== CONTRACT NUMBER GENERATION ====================
    /**
     * Generate the next contract number for the current year.
     * Uses a database transaction with row lock to prevent duplicates.
     */
    public static function generateContractNumber()
    {
        $year = now()->year;

        return DB::transaction(function () use ($year) {
            $max = static::where('contract_year', $year)
                ->lockForUpdate()
                ->max('contract_number') ?? 0;

            $next = $max + 1;

            return [
                'number' => $next,
                'year'   => $year,
            ];
        });
    }

    /**
     * Accessor to get the formatted contract number (e.g. "2026/0001")
     */
    public function getFormattedContractNumberAttribute()
    {
        if ($this->contract_number && $this->contract_year) {
            return $this->contract_year . '/' . str_pad($this->contract_number, 4, '0', STR_PAD_LEFT);
        }
        return null;
    }

    // ==================== EXISTING BUSINESS LOGIC ====================

    /**
     * Calculate rental days from start_date and end_date.
     */
    public function calculateRentalDays()
    {
        if ($this->start_date && $this->end_date) {
            $start = \Carbon\Carbon::parse($this->start_date);
            $end = \Carbon\Carbon::parse($this->end_date);
            return $end->diffInDays($start) + 1;
        }
        return $this->rental_days ?? 1;
    }

    /**
     * Calculate total price based on rental days and car price.
     */
    public function calculateTotalPrice()
    {
        $days = $this->rental_days ?? $this->calculateRentalDays();
        $carPrice = $this->car ? $this->car->price_per_day : 0;
        return $days * $carPrice;
    }

    /**
     * Add a payment to the payment history.
     */
    public function addPayment($amount, $date = null, $method = 'cash', $notes = '')
    {
        $paymentHistory = $this->payment_history ?? [];
        
        $paymentHistory[] = [
            'id' => uniqid(),
            'amount' => (float) $amount,
            'date' => $date ?: now()->toDateString(),
            'method' => $method,
            'notes' => $notes,
            'created_at' => now()->toDateTimeString()
        ];

        $this->payment_history = $paymentHistory;
        
        // Update amount_paid and remaining_amount
        $this->amount_paid = (float) $this->amount_paid + (float) $amount;
        $this->remaining_amount = (float) $this->total_price - (float) $this->amount_paid;
        
        return $this;
    }

    /**
     * Get total paid from payment history.
     */
    public function getTotalPaidFromHistory()
    {
        if (empty($this->payment_history)) {
            return 0;
        }
        return array_sum(array_column($this->payment_history, 'amount'));
    }

    /**
     * Remove a payment from history by its ID.
     */
    public function removePayment($paymentId)
    {
        $paymentHistory = $this->payment_history ?? [];
        
        $updatedHistory = array_filter($paymentHistory, function($payment) use ($paymentId) {
            return $payment['id'] !== $paymentId;
        });
        
        $this->payment_history = array_values($updatedHistory);
        
        // Recalculate amount_paid and remaining_amount
        $this->amount_paid = $this->getTotalPaidFromHistory();
        $this->remaining_amount = (float) $this->total_price - (float) $this->amount_paid;
        
        return $this;
    }

    /**
     * Get both clients (locataire and second driver).
     */
    public function getAllClients()
    {
        $clients = [$this->client];
        
        if ($this->has_second_driver && $this->secondDriverClient) {
            $clients[] = $this->secondDriverClient;
        }
        
        return $clients;
    }

    /**
     * Check if the reservation has a second driver.
     */
    public function hasSecondDriver()
    {
        return $this->has_second_driver && $this->secondDriverClient;
    }
    public function sousLocation()
{
    return $this->belongsTo(SousLocation::class, 'sous_location_id');
}
}