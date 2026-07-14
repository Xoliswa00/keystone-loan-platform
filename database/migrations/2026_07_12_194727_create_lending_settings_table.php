<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lending_settings is a SINGLETON — one row per deployment, same pattern as
 * the companies table (see 2026_06_02_000020_add_settings_to_companies.php).
 * Centralizes policy knobs that were previously hardcoded consts / config()
 * values scattered across CustomerLimitationService, AffordabilityService,
 * CloDecisionEngine, BadDebtProvisionService, RepaymentSchedule, and the
 * EscalateArrears/SendPaymentReminders console commands — so changing them
 * no longer requires a code deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lending_settings', function (Blueprint $table) {
            $table->id();

            // Application limits (CustomerLimitationService::canApply())
            $table->unsignedTinyInteger('max_active_loans')->default(1)
                ->comment('Max concurrent active (non-settled) loans per client before new applications are blocked');
            $table->unsignedSmallInteger('rejection_cooldown_days')->default(30)
                ->comment('NCR reckless-lending cooldown — days a rejected client must wait before reapplying');

            // Affordability (AffordabilityService)
            $table->decimal('affordability_ratio', 4, 2)->default(0.30)
                ->comment('NCA: max instalment as a fraction of disposable income');

            // CLO advisory risk bands (CloDecisionEngine)
            $table->unsignedTinyInteger('clo_risk_review_threshold')->default(40);
            $table->unsignedTinyInteger('clo_risk_escalate_threshold')->default(75);

            // IFRS 9 staging / provisioning (BadDebtProvisionService, RepaymentSchedule::ifrs9Stage())
            $table->unsignedSmallInteger('ifrs9_stage2_dpd')->default(30)
                ->comment('DPD at which a loan moves to IFRS 9 Stage 2 (under-performing)');
            $table->unsignedSmallInteger('ifrs9_stage3_dpd')->default(90)
                ->comment('DPD at which a loan moves to IFRS 9 Stage 3 (non-performing / credit-impaired)');
            $table->unsignedSmallInteger('ifrs9_writeoff_dpd')->default(180)
                ->comment('DPD at which a loan is automatically written off');
            $table->decimal('ifrs9_stage1_rate', 4, 2)->default(0.02);
            $table->decimal('ifrs9_stage2_rate', 4, 2)->default(0.20);
            $table->decimal('ifrs9_stage3_rate', 4, 2)->default(0.80);

            // Arrears escalation emails (EscalateArrears command)
            $table->unsignedSmallInteger('arrears_second_notice_dpd')->default(60)
                ->comment('DPD at which the intermediate "second notice" escalation email tier fires — between ifrs9_stage2_dpd and ifrs9_stage3_dpd by default, independently tunable');

            // Payment reminders (SendPaymentReminders command)
            $table->unsignedTinyInteger('payment_reminder_days_before_due')->default(3)
                ->comment('Default days-before-due-date for the payment reminder cron; --days on the command still overrides for manual runs');

            $table->timestamps();
        });

        // Seed data (the singleton defaults row) lives in
        // database/seeders/LendingSettingsSeeder.php, not here.
    }

    public function down(): void
    {
        Schema::dropIfExists('lending_settings');
    }
};
