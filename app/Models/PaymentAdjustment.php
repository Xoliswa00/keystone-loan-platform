<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A shortfall or credit created when ManualPaymentService/NuPayService
 * accept a payment that's short or over by up to
 * LendingSetting::payment_tolerance_pct — one row per event, not per
 * customer, so FIFO consumption (oldest first) and the audit trail both
 * stay granular. See PaymentAdjustmentService for all lifecycle logic;
 * this model is deliberately data-only.
 */
class PaymentAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'source_loan_id',
        'source_repayment_schedule_id',
        'source_loan_repayment_id',
        'type',
        'original_amount',
        'outstanding_amount',
        'status',
        'applied_to_loan_id',
        'applied_to_schedule_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    // ── Relationships ──

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceLoan()
    {
        return $this->belongsTo(Loan::class, 'source_loan_id');
    }

    public function sourceSchedule()
    {
        return $this->belongsTo(RepaymentSchedule::class, 'source_repayment_schedule_id');
    }

    public function sourceRepayment()
    {
        return $this->belongsTo(LoanRepayment::class, 'source_loan_repayment_id');
    }

    public function appliedToLoan()
    {
        return $this->belongsTo(Loan::class, 'applied_to_loan_id');
    }

    public function appliedToSchedule()
    {
        return $this->belongsTo(RepaymentSchedule::class, 'applied_to_schedule_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['outstanding', 'partially_applied']);
    }

    public function scopeShortfalls($query)
    {
        return $query->where('type', 'shortfall');
    }

    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }
}
