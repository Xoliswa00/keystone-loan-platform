<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    use HasFactory;

    protected $table = 'loan_applications';

    protected $fillable = [
        'user_id',
        'loan_product_id',
        'loan_type',
        'loan_term_months',
        'loan_amount',
        'purpose',
        'collateral',
        'terms_conditions',
        'status',
        'reviewer_id',
        'approval_date',
        'reason',
        'arrears',
        'credit_score_report',
        'bank_statement',
        'payslips',
        'affordability_checked',
        'affordability_disposable_income',
        'affordability_max_instalment',
        'affordability_instalment_requested',
    ];

    protected $casts = [
        'terms_conditions'                 => 'boolean',
        'affordability_checked'            => 'boolean',
        'affordability_disposable_income'  => 'decimal:2',
        'affordability_max_instalment'     => 'decimal:2',
        'affordability_instalment_requested'=> 'decimal:2',
        'loan_amount'                      => 'decimal:2',
        'approval_date'                    => 'datetime',
    ];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    // repayment_schedules.loan_id references loan_applications.id
    public function repaymentSchedules()
    {
        return $this->hasMany(RepaymentSchedule::class, 'loan_id', 'id');
    }

    public function loanfee()
    {
        return $this->hasOne(LoanFee::class, 'loan_application_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id', 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'under_review', 'approved']);
    }
}
