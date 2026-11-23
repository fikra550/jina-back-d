<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Accident extends Model
{
    use HasFactory;

    protected $fillable = [
        'accident_number',
        'reservation_id',
        'matricule_id',
        'car_id',
        'client_id',
        'date_accident',
        'time_accident',
        'location',
        'description',
        'police_report_number',
        'amount_of_losses',
        'amount_assurance',
        'nom_expert',
        'status',
        'accident_type',
        'procedure_type',
        'expert_decision',
        'expert_amount',
        'expert_notes',
        'notes',
        'img_accident',
        'img_evaluation_expert',
        'img_fixed',
        'image_facture',
        'garage_id',
        'inspection_notes',
        'estimated_cost',
        'total_repair_cost',
        'franchise_amount',
        'insurance_paid',
        'client_paid',
        'total_paid',
        'remaining_amount',
        'internal_notes',
        'closed_at',
        'estimate_items',
        'estimate_total_ht',
        'estimate_tva',
        'estimate_total_ttc',
        'estimate_status',
        'estimate_number',   // NEW
        'repair_start_date',
        'repair_end_date',
        'repair_notes',
        'invoice_number',
        'invoice_items',
        'invoice_total_ht',
        'invoice_tva',
        'invoice_total_ttc',
        'payments',
    ];

    protected $casts = [
        'date_accident' => 'date',
        'time_accident' => 'datetime:H:i',
        'img_accident' => 'array',
        'img_evaluation_expert' => 'array',
        'img_fixed' => 'array',
        'image_facture' => 'array',
        'estimated_cost' => 'decimal:2',
        'total_repair_cost' => 'decimal:2',
        'franchise_amount' => 'decimal:2',
        'insurance_paid' => 'decimal:2',
        'client_paid' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'closed_at' => 'datetime',
        'estimate_items' => 'array',
        'estimate_total_ht' => 'decimal:2',
        'estimate_tva' => 'decimal:2',
        'estimate_total_ttc' => 'decimal:2',
        'invoice_items' => 'array',
        'invoice_total_ht' => 'decimal:2',
        'invoice_tva' => 'decimal:2',
        'invoice_total_ttc' => 'decimal:2',
        'payments' => 'array',
        'repair_start_date' => 'date',
        'repair_end_date' => 'date',
        'estimate_status' => 'string',
    ];

    // Relations
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function matricule()
    {
        return $this->belongsTo(Matricule::class);
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function garage()
    {
        return $this->belongsTo(Garage::class);
    }

    // Accessors for image URLs
    public function getImgAccidentUrlsAttribute()
    {
        return $this->getImageUrls($this->img_accident);
    }

    public function getImgEvaluationExpertUrlsAttribute()
    {
        return $this->getImageUrls($this->img_evaluation_expert);
    }

    public function getImgFixedUrlsAttribute()
    {
        return $this->getImageUrls($this->img_fixed);
    }

    public function getImageFactureUrlsAttribute()
    {
        return $this->getImageUrls($this->image_facture);
    }

    private function getImageUrls($images)
    {
        if (!$images || !is_array($images)) {
            return [];
        }

        return array_map(function ($image) {
            return Storage::disk('public')->exists($image)
                ? Storage::disk('public')->url($image)
                : null;
        }, $images);
    }

    // Status options
    public static function getStatusOptions()
    {
        return [
            'open' => 'Ouvert',
            'under_review' => 'En cours d\'examen',
            'waiting_estimate' => 'En attente de devis',
            'estimate_approved' => 'Devis approuvé',
            'under_repair' => 'En réparation',
            'invoice_received' => 'Facture reçue',
            'waiting_payment' => 'En attente de paiement',
            'closed' => 'Clos',
        ];
    }

    public function getStatusLabelAttribute()
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'open' => 'badge-warning',
            'under_review' => 'badge-purple',
            'waiting_estimate' => 'badge-pink',
            'estimate_approved' => 'badge-indigo',
            'under_repair' => 'badge-orange',
            'invoice_received' => 'badge-rose',
            'waiting_payment' => 'badge-yellow',
            'closed' => 'badge-success',
        ];
        return $colors[$this->status] ?? 'badge-secondary';
    }

    // Next possible statuses
    public function getNextPossibleStatuses()
    {
        $statusFlow = [
            'open' => ['under_review'],
            'under_review' => ['waiting_estimate'],
            'waiting_estimate' => ['estimate_approved'],
            'estimate_approved' => ['under_repair'],
            'under_repair' => ['invoice_received'],
            'invoice_received' => ['waiting_payment'],
            'waiting_payment' => ['closed'],
            'closed' => [],
        ];
        return $statusFlow[$this->status] ?? [];
    }

    public function getTotalRemainingAttribute()
    {
        return ($this->total_repair_cost ?? 0) - $this->total_paid;
    }

    public function getProgressPercentageAttribute()
    {
        if (!$this->total_repair_cost || $this->total_repair_cost <= 0) {
            return 0;
        }
        return min(100, round(($this->total_paid / $this->total_repair_cost) * 100));
    }

    // Recalculate payment totals
    public function calculatePaymentTotals()
    {
        $payments = $this->payments ?? [];
        $clientTotal = 0;
        $insuranceTotal = 0;

        foreach ($payments as $payment) {
            if ($payment['payer'] === 'client') {
                $clientTotal += $payment['amount'];
            } elseif ($payment['payer'] === 'insurance') {
                $insuranceTotal += $payment['amount'];
            }
        }

        $this->client_paid = $clientTotal;
        $this->insurance_paid = $insuranceTotal;
        $this->total_paid = $clientTotal + $insuranceTotal;
        $this->remaining_amount = max(0, ($this->total_repair_cost ?? 0) - $this->total_paid);

        return $this;
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($accident) {
            if (empty($accident->accident_number)) {
                $year = date('Y');
                $month = date('m');
                $count = self::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count() + 1;
                $accident->accident_number = 'ACC-' . $year . '-' . $month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function ($accident) {
            $accident->calculatePaymentTotals();

            if ($accident->status === 'waiting_payment' &&
                $accident->total_paid >= ($accident->total_repair_cost ?? 0) &&
                $accident->total_repair_cost > 0) {
                $accident->status = 'closed';
                $accident->closed_at = now();
            }
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'closed');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            'under_review',
            'waiting_estimate',
            'estimate_approved',
            'under_repair',
            'invoice_received',
            'waiting_payment'
        ]);
    }
}