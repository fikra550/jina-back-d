<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaveurPayment extends Model
{
    protected $fillable = [
        'laveur_id',
        'amount',
        'payment_date',
        'method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'float',
        'payment_date' => 'date:Y-m-d',
    ];

    public function laveur(): BelongsTo
    {
        return $this->belongsTo(Laveur::class);
    }
}