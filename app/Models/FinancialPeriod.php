<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FinancialPeriod extends Model
{
    protected $fillable = [
        'period', 'fiscal_year', 'fiscal_month', 'is_year_end', 'status',
        'provisioning_complete', 'facility_interest_accrued',
        'bank_recon_complete', 'bank_recon_no_activity', 'trial_balance_generated',
        'gl_closing_balances', 'period_income', 'period_expenses', 'period_net_profit',
        'year_end_gl_batch_ref', 'year_end_complete',
        'closed_by', 'closed_at', 'locked_by', 'locked_at', 'notes',
    ];

    protected $casts = [
        'is_year_end' => 'boolean',
        'provisioning_complete' => 'boolean',
        'facility_interest_accrued' => 'boolean',
        'bank_recon_complete' => 'boolean',
        'bank_recon_no_activity' => 'boolean',
        'trial_balance_generated' => 'boolean',
        'year_end_complete' => 'boolean',
        'gl_closing_balances' => 'array',
        'period_income' => 'decimal:2',
        'period_expenses' => 'decimal:2',
        'period_net_profit' => 'decimal:2',
        'closed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    const STATUS_CLASSES = [
        'open' => 'kc-badge-green',
        'closing' => 'kc-badge-gold',
        'closed' => 'kc-badge-navy',
        'locked' => 'kc-badge-silver',
    ];

    // ── Relationships ──

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // ── Helpers ──

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosing(): bool
    {
        return $this->status === 'closing';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'locked']);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function canPost(): bool
    {
        return in_array($this->status, ['open', 'closing']);
    }

    public function checklistComplete(): bool
    {
        return $this->provisioning_complete
            && $this->facility_interest_accrued
            && $this->bank_recon_complete
            && $this->trial_balance_generated;
    }

    public function checklistProgress(): int
    {
        $done = (int) $this->provisioning_complete
              + (int) $this->facility_interest_accrued
              + (int) $this->bank_recon_complete
              + (int) $this->trial_balance_generated;

        return (int) round(($done / 4) * 100);
    }

    public function displayLabel(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
    }

    // ── Static helpers ──

    public static function current(): ?self
    {
        return static::where('period', now()->format('Y-m'))->first();
    }

    public static function forDate(string $date): ?self
    {
        $period = Carbon::parse($date)->format('Y-m');

        return static::where('period', $period)->first();
    }

    /**
     * Ensure a period exists for the given YYYY-MM, creating it as 'open' if not.
     */
    public static function ensure(string $period): self
    {
        return static::firstOrCreate(
            ['period' => $period],
            [
                'fiscal_year' => (int) substr($period, 0, 4),
                'fiscal_month' => (int) substr($period, 5, 2),
                'is_year_end' => (int) substr($period, 5, 2) === 12,
                'status' => 'open',
            ]
        );
    }
}
