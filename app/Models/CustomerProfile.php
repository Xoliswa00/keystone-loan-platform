<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employer_name',
        'employer_phone',
        'employment_type',
        'employment_tenure',
        'gross_monthly_income',
        'net_monthly_income',
        'other_income',
        'other_income_source',
        'expense_housing',
        'expense_transport',
        'expense_existing_debt',
        'expense_insurance',
        'expense_living',
        'marital_status',
        'dependants',
        'total_monthly_expenses',
        'disposable_income',
        'max_monthly_instalment',
        'profile_complete',
        'completed_at',
    ];

    protected $casts = [
        'gross_monthly_income' => 'decimal:2',
        'net_monthly_income' => 'decimal:2',
        'other_income' => 'decimal:2',
        'expense_housing' => 'decimal:2',
        'expense_transport' => 'decimal:2',
        'expense_existing_debt' => 'decimal:2',
        'expense_insurance' => 'decimal:2',
        'expense_living' => 'decimal:2',
        'total_monthly_expenses' => 'decimal:2',
        'disposable_income' => 'decimal:2',
        'max_monthly_instalment' => 'decimal:2',
        'profile_complete' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Computed ──

    public function getTotalExpensesAttribute(): float
    {
        return (float) $this->expense_housing
             + (float) $this->expense_transport
             + (float) $this->expense_existing_debt
             + (float) $this->expense_insurance
             + (float) $this->expense_living;
    }

    public function getTotalIncomeAttribute(): float
    {
        return (float) ($this->net_monthly_income ?? 0)
             + (float) ($this->other_income ?? 0);
    }

    public function getCalculatedDisposableAttribute(): float
    {
        return $this->total_income - $this->total_expenses;
    }

    public function getCalculatedMaxInstalmentAttribute(): float
    {
        return max(0, $this->calculated_disposable * 0.30);
    }

    // ── Recalculate derived fields and save ──

    public function recalculate(): void
    {
        $this->total_monthly_expenses = $this->total_expenses;
        $this->disposable_income = $this->calculated_disposable;
        $this->max_monthly_instalment = $this->calculated_max_instalment;

        $this->profile_complete = $this->net_monthly_income > 0
            && ($this->expense_housing > 0 || $this->expense_living > 0);

        if ($this->profile_complete && ! $this->completed_at) {
            $this->completed_at = now();
        }

        $this->saveQuietly();
    }
}
