<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class LoanProduct extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (LoanProduct $product) {
            $product->assertNcaCompliant();
        });
    }

    /**
     * Guard against configuring a product outside NCA-prescribed maximums
     * (Regulation 42 fee caps, s.103(5) is enforced separately at the loan
     * level). Runs on every save — tinker, seeder, or future admin UI alike.
     */
    public function assertNcaCompliant(): void
    {
        $errors = [];

        $rateCap = (float) config('nca.interest_rate_cap_monthly');
        if ((float) $this->monthly_interest_rate > $rateCap) {
            $errors[] = "monthly_interest_rate ({$this->monthly_interest_rate}) exceeds the NCA short-term credit cap of {$rateCap} per month.";
        }

        $initiationAbsoluteCap = (float) config('nca.initiation_fee_absolute_cap');
        if ((float) $this->initiation_fee_cap > $initiationAbsoluteCap) {
            $errors[] = "initiation_fee_cap ({$this->initiation_fee_cap}) exceeds the NCA absolute initiation fee cap of R{$initiationAbsoluteCap} (excl. VAT).";
        }

        $initiationFlatCap = (float) config('nca.initiation_fee_flat_cap');
        if ((float) $this->initiation_fee_flat > $initiationFlatCap) {
            $errors[] = "initiation_fee_flat ({$this->initiation_fee_flat}) exceeds the NCA flat initiation fee cap of R{$initiationFlatCap} (excl. VAT).";
        }

        $serviceFeeCap = (float) config('nca.monthly_service_fee_cap');
        if ((float) $this->monthly_service_fee > $serviceFeeCap) {
            $errors[] = "monthly_service_fee ({$this->monthly_service_fee}) exceeds the NCA monthly service fee cap of R{$serviceFeeCap} (excl. VAT) without a documented repo-rate escalation.";
        }

        if ($errors) {
            throw ValidationException::withMessages(['nca_compliance' => $errors]);
        }
    }

    protected $fillable = [
        'name', 'code',
        'min_amount', 'max_amount',
        'min_months', 'max_months',
        'monthly_interest_rate',
        'initiation_fee_flat', 'initiation_fee_rate', 'initiation_fee_cap',
        'monthly_service_fee',
        'vat_rate',
        'requires_enhanced_affordability',
        'active',
        'description',
    ];

    protected $casts = [
        'min_amount'                      => 'decimal:2',
        'max_amount'                      => 'decimal:2',
        'monthly_interest_rate'           => 'decimal:4',
        'initiation_fee_flat'             => 'decimal:2',
        'initiation_fee_rate'             => 'decimal:4',
        'initiation_fee_cap'              => 'decimal:2',
        'monthly_service_fee'             => 'decimal:2',
        'vat_rate'                        => 'decimal:4',
        'requires_enhanced_affordability' => 'boolean',
        'active'                          => 'boolean',
    ];

    // ── Relationships ──

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    // ── Helpers ──

    /**
     * Calculate the NCA-compliant initiation fee for a given loan amount.
     * Fee = flat + (rate × amount over R1,000), capped at initiation_fee_cap, then + VAT.
     */
    public function initiationFee(float $amount): float
    {
        $base  = $this->initiation_fee_flat;
        $base += $this->initiation_fee_rate * max(0, $amount - 1000);
        $base  = min($base, $this->initiation_fee_cap);

        return round($base * (1 + $this->vat_rate), 2); // include VAT
    }

    /**
     * Monthly service fee including VAT.
     */
    public function serviceFeWithVat(): float
    {
        return round($this->monthly_service_fee * (1 + $this->vat_rate), 2);
    }

    /**
     * Calculate total repayment for a loan — principal, interest, fees.
     * Returns a breakdown array.
     */
    public function calculateRepayment(float $principal, int $months): array
    {
        $monthlyRate   = (float) $this->monthly_interest_rate;
        $totalInterest = round($principal * $monthlyRate * $months, 2);

        $initiationFee = $this->initiationFee($principal);
        $serviceFeeTotal = round($this->serviceFeWithVat() * $months, 2);

        $totalFees  = $initiationFee + $serviceFeeTotal;
        $totalDue   = $principal + $totalInterest + $totalFees;
        $instalment = round($totalDue / $months, 2);

        // Distribute rounding cents across installments
        $remainder  = round($totalDue - ($instalment * $months), 2);

        return [
            'principal'          => $principal,
            'monthly_rate'       => $monthlyRate,
            'total_interest'     => $totalInterest,
            'initiation_fee'     => $initiationFee,
            'service_fee_total'  => $serviceFeeTotal,
            'total_fees'         => $totalFees,
            'total_due'          => round($totalDue, 2),
            'base_instalment'    => $instalment,
            'rounding_remainder' => $remainder,
            'months'             => $months,
        ];
    }

    /**
     * Per-installment split: principal, interest, fee for installment $i (0-indexed).
     */
    public function installmentSplit(float $principal, int $months, int $index, float $baseInstalment, float $remainder): array
    {
        $emi = $baseInstalment + ($index === ($months - 1) ? $remainder : 0);

        $monthlyInterest = round($principal * (float) $this->monthly_interest_rate, 2);
        $monthlyService  = $this->serviceFeWithVat();
        $initiationSlice = round($this->initiationFee($principal) / $months, 2);

        $feeSlice        = $monthlyService + $initiationSlice;
        $principalSlice  = round($emi - $monthlyInterest - $feeSlice, 2);

        return [
            'emi_amount'       => round($emi, 2),
            'principal_amount' => $principalSlice,
            'interest_amount'  => $monthlyInterest,
            'fee_amount'       => round($feeSlice, 2),
        ];
    }
}
