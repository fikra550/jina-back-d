<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'rows',
        'total_ht'
    ];

    protected $casts = [
        'rows' => 'array',
        'total_ht' => 'decimal:2'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'user_id');
    }
}