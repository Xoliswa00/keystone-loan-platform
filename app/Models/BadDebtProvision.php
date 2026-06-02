<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadDebtProvision extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'user_id',
        'ifrs9_stage',
        'days_past_due',
        'outstanding_balance',
        'provision_rate',
        'provision_amount',
        'prior_provision',
        'provision_movement',
        'gl_batch_reference',
        'gl_posted',
        'created_by',
        'provision_date',
        'notes',
    ];

    protected $casts = [
        'outstanding_balance' => 'decimal:2',
        'provision_rate'      => 'decimal:4',
        'provision_amount'    => 'decimal:2',
        'prior_provision'     => 'decimal:2',
        'provision_movement'  => 'decimal:2',
        'gl_posted'           => 'boolean',
        'provision_date'      => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStageLabelAttribute(): string
    {
        return match($this->ifrs9_stage) {
            1 => 'Stage 1 — Performing',
            2 => 'Stage 2 — Under-performing',
            3 => 'Stage 3 — Non-performing',
            default => 'Unknown',
        };
    }
}
