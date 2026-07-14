<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial Periods — proper period management.
 *
 * Replaces the basic period_closes log.
 * Every GL posting is validated against the period status.
 *
 * Lifecycle:
 *   open    → transactions can be posted freely
 *   closing → period-end process started; new postings still allowed (adjusting entries)
 *   closed  → no new postings; financial statements can be generated
 *   locked  → immutable; audited and signed off
 *
 * Year-end:
 *   The 12th period of a fiscal year triggers year-end close:
 *   Dr Income accounts / Cr Retained Earnings
 *   Dr Retained Earnings / Cr Expense accounts
 *   Net profit/loss transferred to equity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();        // YYYY-MM
            $table->smallInteger('fiscal_year');           // YYYY
            $table->unsignedTinyInteger('fiscal_month');   // 1–12
            $table->boolean('is_year_end')->default(false);

            $table->enum('status', [
                'open',      // accepting transactions
                'closing',   // period-end in progress; adjusting entries allowed
                'closed',    // no new postings; financial statements generated
                'locked',    // fully signed off; immutable
            ])->default('open');

            // Pre-close checklist — all must be true before status → closed
            $table->boolean('provisioning_complete')->default(false);
            $table->boolean('facility_interest_accrued')->default(false);
            $table->boolean('bank_recon_complete')->default(false);
            $table->boolean('trial_balance_generated')->default(false);

            // GL balance snapshot at close — for audit trail
            // Stored as JSON: {"1100": 45000.00, "1200": 320000.00, ...}
            $table->json('gl_closing_balances')->nullable();

            // P&L summary at close
            $table->decimal('period_income', 15, 2)->nullable();
            $table->decimal('period_expenses', 15, 2)->nullable();
            $table->decimal('period_net_profit', 15, 2)->nullable();

            // Year-end journal reference
            $table->string('year_end_gl_batch_ref')->nullable();
            $table->boolean('year_end_complete')->default(false);

            // Who closed/locked it
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'fiscal_year']);
        });

        // Seed data (the rolling open-period window) lives in
        // database/seeders/FinancialPeriodsSeeder.php, not here.
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_periods');
    }
};
