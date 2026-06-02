<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundingFacility extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_code', 'facility_name', 'funder_type',
        'funder_name', 'funder_id_or_reg',
        'contact_person', 'contact_email', 'contact_phone',
        'bank_name', 'bank_account_number',
        'facility_limit', 'drawn_amount', 'current_balance',
        'interest_rate', 'interest_type', 'prime_margin', 'accrued_interest',
        'facility_start_date', 'maturity_date',
        'payment_frequency', 'payment_day',
        'status', 'collateral_description', 'notes',
        'agreement_document_path', 'created_by',
    ];

    protected $casts = [
        'facility_limit'       => 'decimal:2',
        'drawn_amount'         => 'decimal:2',
        'current_balance'      => 'decimal:2',
        'interest_rate'        => 'decimal:4',
        'prime_margin'         => 'decimal:4',
        'accrued_interest'     => 'decimal:2',
        'facility_start_date'  => 'date',
        'maturity_date'        => 'date',
    ];

    const FUNDER_TYPES = [
        'shareholder_loan'  => 'Shareholder / Director Loan',
        'bank_credit_line'  => 'Bank Credit Line',
        'private_investor'  => 'Private Investor',
        'family_office'     => 'Family Office',
        'other'             => 'Other',
    ];

    const STATUS_CLASSES = [
        'active'       => 'kc-badge-green',
        'paused'       => 'kc-badge-gold',
        'fully_repaid' => 'kc-badge-silver',
        'in_default'   => 'kc-badge-red',
        'closed'       => 'kc-badge-silver',
    ];

    // ── Relationships ──

    public function transactions()
    {
        return $this->hasMany(FundingFacilityTransaction::class, 'facility_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Computed ──

    public function availableLimit(): float
    {
        return max(0, (float) $this->facility_limit - (float) $this->current_balance);
    }

    public function utilisationRate(): float
    {
        return $this->facility_limit > 0
            ? round(($this->current_balance / $this->facility_limit) * 100, 1)
            : 0;
    }

    /**
     * Calculate monthly interest owed based on current balance and annual rate.
     */
    public function monthlyInterestDue(): float
    {
        return round((float) $this->current_balance * ((float) $this->interest_rate / 12), 2);
    }

    public function isOverdue(): bool
    {
        return $this->payment_day
            && now()->day > $this->payment_day
            && $this->accrued_interest > 0
            && $this->status === 'active';
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
