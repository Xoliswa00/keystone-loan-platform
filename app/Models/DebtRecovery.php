<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtRecovery extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'user_id', 'status',
        'original_write_off_amount', 'total_recovered', 'outstanding_recovery',
        'assigned_to', 'external_agency', 'legal_reference',
        'next_action_date', 'notes', 'opened_at', 'closed_at', 'closed_by',
    ];

    protected $casts = [
        'original_write_off_amount' => 'decimal:2',
        'total_recovered'           => 'decimal:2',
        'outstanding_recovery'      => 'decimal:2',
        'opened_at'                 => 'date',
        'closed_at'                 => 'date',
        'next_action_date'          => 'date',
    ];

    const STATUSES = [
        'open'      => ['label' => 'Open',      'class' => 'kc-badge-navy'],
        'partial'   => ['label' => 'Partial',   'class' => 'kc-badge-gold'],
        'recovered' => ['label' => 'Recovered', 'class' => 'kc-badge-green'],
        'closed'    => ['label' => 'Closed',    'class' => 'kc-badge-silver'],
        'legal'     => ['label' => 'Legal',     'class' => 'kc-badge-red'],
    ];

    public function loan()       { return $this->belongsTo(Loan::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function payments()   { return $this->hasMany(DebtRecoveryPayment::class); }

    public function recoveryRate(): float
    {
        return $this->original_write_off_amount > 0
            ? round(($this->total_recovered / $this->original_write_off_amount) * 100, 2)
            : 0;
    }
}
