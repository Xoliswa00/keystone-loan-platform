<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'loan_application_id',
        'loan_product_id',
        'loan_type',
        'loan_amount',
        'principal_amount',
        'interest_rate',
        'loan_term',
        'loan_term_months',
        'collateral',
        'status',
        'approved_amount',
        'disbursed_date',
        'remaining_balance',
        'approver_id',
        'processed_at',
        'approval_comments',
        'approved_at',
        'next_payment_date',
        'installment_frequency',
        'total_interest',
        'total_fees_excl_vat',
        'total_vat',
        'total_amount_due',
        'deferred_interest',
        'deferred_fees',
    ];

    protected $casts = [
        'loan_amount'        => 'decimal:2',
        'principal_amount'   => 'decimal:2',
        'interest_rate'      => 'decimal:4',
        'approved_amount'    => 'decimal:2',
        'remaining_balance'  => 'decimal:2',
        'total_interest'     => 'decimal:2',
        'total_fees_excl_vat'=> 'decimal:2',
        'total_vat'          => 'decimal:2',
        'total_amount_due'   => 'decimal:2',
        'deferred_interest'  => 'decimal:2',
        'deferred_fees'      => 'decimal:2',
        'disbursed_date'     => 'date',
        'approved_at'        => 'datetime',
        'processed_at'       => 'datetime',
        'next_payment_date'  => 'date',
    ];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function repaymentSchedules()
    {
        return $this->hasMany(RepaymentSchedule::class, 'loan_id');
    }

    public function fees()
    {
        return $this->hasOne(LoanFee::class);
    }

    public function disbursements()
    {
        return $this->hasMany(LoanDisbursement::class, 'loan_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function interest()
    {
        return $this->hasOne(LoanInterest::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['approved', 'disbursed']);
    }

    public function scopeDisbursed($query)
    {
        return $query->where('status', 'disbursed');
    }

    // ── Helpers ──

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    public function isDisbursed(): bool
    {
        return $this->status === 'disbursed';
    }

    /**
     * NCA s.103(5) in duplum rule: interest, default charges, and fees that
     * accrue while a consumer is in default may never exceed the unpaid
     * principal balance as at the moment default occurred. There is no
     * default/penalty-interest accrual path in this codebase yet — this is
     * the guard any such feature (e.g. late fees, arrears interest) must
     * call before posting a new charge against a defaulted loan.
     */
    public function exceedsInDuplumCap(float $additionalCharge = 0.0): bool
    {
        $capMultiple = (float) config('nca.in_duplum_multiple');
        $principal   = (float) $this->principal_amount;
        $chargedSoFar = (float) $this->total_interest + (float) $this->total_fees_excl_vat;

        return ($chargedSoFar + $additionalCharge) > ($principal * $capMultiple);
    }
}
