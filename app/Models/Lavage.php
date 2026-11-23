<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Lavage extends Model
{
    protected $fillable = [
        'matricule_id', 'car_id', 'client_id', 'client_name', 'reservation_id',
        'date_lavage', 'time_lavage',
        'started_at', 'completed_at', 'duration_minutes',
        'type', 'services',
        'price', 'subtotal', 'discount_amount', 'tva_amount',
        'status', 'payment_status', 'amount_paid', 'payment_history',
        'photos_before', 'photos_after',
        'washer_name', 'assigned_to', 'bay',
        'vehicle_condition_before', 'vehicle_condition_after',
        'rating', 'feedback',
        'notes', 'created_by',
    ];

    protected $casts = [
        'date_lavage'      => 'date',
        'price'            => 'float',
        'subtotal'         => 'float',
        'discount_amount'  => 'float',
        'tva_amount'       => 'float',
        'amount_paid'      => 'float',
        'services'         => 'array',
        'payment_history'  => 'array',
        'photos_before'    => 'array',
        'photos_after'     => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'duration_minutes' => 'integer',
        'rating'           => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($l) {
            if ($l->status === null)         $l->status         = 'pending';
            if ($l->payment_status === null) $l->payment_status = 'unpaid';
            if ($l->price === null)          $l->price          = 0;
            if ($l->amount_paid === null)    $l->amount_paid    = 0;
            if ($l->payment_history === null) $l->payment_history = [];
        });
    }

    /* Relations */
    public function matricule()    { return $this->belongsTo(Matricule::class); }
    public function car()          { return $this->belongsTo(Car::class); }
    public function client()       { return $this->belongsTo(Client::class); }
    public function reservation()  { return $this->belongsTo(Reservation::class); }
    public function assignedUser() { return $this->belongsTo(Utilisateur::class, 'assigned_to'); }

    public function lavageProducts() { return $this->hasMany(LavageProduct::class); }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'lavage_products')
                    ->withPivot(['quantity', 'unit_cost', 'total_cost'])
                    ->withTimestamps();
    }

    /* Accessors */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->price - (float) $this->amount_paid);
    }

    public function getPhotosBeforeUrlsAttribute(): array
    {
        return collect($this->photos_before ?? [])
            ->filter(fn($p) => $p && Storage::disk('public')->exists($p))
            ->map(fn($p) => Storage::disk('public')->url($p))
            ->values()->toArray();
    }

    public function getPhotosAfterUrlsAttribute(): array
    {
        return collect($this->photos_after ?? [])
            ->filter(fn($p) => $p && Storage::disk('public')->exists($p))
            ->map(fn($p) => Storage::disk('public')->url($p))
            ->values()->toArray();
    }

    /* Payments */
    public function addPayment(float $amount, string $date, string $method = 'cash', string $notes = ''): void
    {
        $history   = $this->payment_history ?? [];
        $history[] = [
            'id'         => uniqid('lav_pay_'),
            'amount'     => $amount,
            'date'       => $date,
            'method'     => $method,
            'notes'      => $notes,
            'created_at' => now()->toDateTimeString(),
        ];
        $this->payment_history = $history;
        $this->recalculatePaymentTotals();
    }

    public function removePayment(string $paymentId): bool
    {
        $history  = $this->payment_history ?? [];
        $filtered = array_values(array_filter($history, fn($p) => $p['id'] !== $paymentId));
        if (count($filtered) === count($history)) return false;

        $this->payment_history = $filtered;
        $this->recalculatePaymentTotals();
        return true;
    }

    public function recalculatePaymentTotals(): void
    {
        $history = $this->payment_history ?? [];
        $paid    = array_sum(array_map(fn($p) => (float) ($p['amount'] ?? 0), $history));

        $this->amount_paid = $paid;

        if ($paid <= 0) {
            $this->payment_status = 'unpaid';
        } elseif ($paid >= (float) $this->price) {
            $this->payment_status = 'paid';
        } else {
            $this->payment_status = 'partial';
        }
    }

    /* Workflow */
    public function startNow(): void
    {
        if ($this->started_at) return;
        $this->started_at = now();
        $this->status     = 'in_progress';
    }

    public function completeNow(): void
    {
        if ($this->completed_at) return;

        if (!$this->started_at) $this->started_at = now();

        $this->completed_at     = now();
        $this->duration_minutes = (int) $this->started_at->diffInMinutes($this->completed_at);
        $this->status           = 'completed';
    }
}