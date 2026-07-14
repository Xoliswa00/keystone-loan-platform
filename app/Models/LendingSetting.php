<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The LendingSetting model is a singleton — one record per deployment.
 * Centralizes lending/risk policy knobs previously hardcoded across
 * CustomerLimitationService, AffordabilityService, CloDecisionEngine,
 * BadDebtProvisionService, RepaymentSchedule, and the arrears/reminder
 * console commands — same pattern as Company/CompanySettingsController.
 */
class LendingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'max_active_loans',
        'rejection_cooldown_days',
        'affordability_ratio',
        'clo_risk_review_threshold',
        'clo_risk_escalate_threshold',
        'ifrs9_stage2_dpd',
        'ifrs9_stage3_dpd',
        'ifrs9_writeoff_dpd',
        'ifrs9_stage1_rate',
        'ifrs9_stage2_rate',
        'ifrs9_stage3_rate',
        'arrears_second_notice_dpd',
        'payment_reminder_days_before_due',
    ];

    protected $casts = [
        'affordability_ratio' => 'decimal:2',
        'ifrs9_stage1_rate' => 'decimal:2',
        'ifrs9_stage2_rate' => 'decimal:2',
        'ifrs9_stage3_rate' => 'decimal:2',
    ];

    /**
     * Singleton accessor — cached for 10 minutes. firstOrCreate(), not
     * first(): every consumer (CustomerLimitationService, CloDecisionEngine,
     * etc.) calls this expecting a real object back, never null. Normally
     * LendingSettingsSeeder creates this row, but self-healing here means
     * the app degrades to sane defaults instead of a hard TypeError if that
     * row is ever missing (a fresh test database, a manual reset, etc.).
     */
    public static function current(): self
    {
        return Cache::remember('lending_settings', 600, fn () => static::firstOrCreate([], [
            'max_active_loans' => 1,
            'rejection_cooldown_days' => 30,
            'affordability_ratio' => 0.30,
            'clo_risk_review_threshold' => 40,
            'clo_risk_escalate_threshold' => 75,
            'ifrs9_stage2_dpd' => 30,
            'ifrs9_stage3_dpd' => 90,
            'ifrs9_writeoff_dpd' => 180,
            'ifrs9_stage1_rate' => 0.02,
            'ifrs9_stage2_rate' => 0.20,
            'ifrs9_stage3_rate' => 0.80,
            'arrears_second_notice_dpd' => 60,
            'payment_reminder_days_before_due' => 3,
        ]));
    }

    /** Bust cache when settings are saved */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('lending_settings'));
    }
}
