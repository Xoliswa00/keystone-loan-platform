<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flat, pre-computed per-loan row for outbound reporting (SACRRA and any
     * future bureau/partner). One row per loan per (period, generation).
     *
     * Every value here is materialised by AccountSnapshotService::buildForPeriod()
     * so that an export profile can select columns with ZERO calculation.
     * Delinquency figures (DPD, arrears, payment profile) do not exist anywhere
     * else in the schema — they are recomputed on demand from repayment_schedules.
     *
     * `generation` makes a rebuilt period a NEW immutable copy rather than an
     * overwrite: once an export_run has consumed (period, generation) those rows
     * must never change, or the evidentiary record of what was actually reported
     * is lost.
     */
    public function up(): void
    {
        Schema::create('reporting_account_snapshots', function (Blueprint $table) {
            $table->id();

            $table->char('period', 7)->comment('Reporting month, YYYY-MM (period end)');
            $table->unsignedInteger('generation')->default(1)->comment('Rebuild counter for this period; higher = newer immutable copy');

            // ── Keys (traceability; not necessarily exported) ──
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedBigInteger('loan_application_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->string('account_no')->comment('Stable per-credit-agreement subscriber number, e.g. KL-000123 — never customer_code');

            // ── Borrower identity ──
            $table->string('borrower_name')->nullable();
            $table->string('id_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('nationality', 3)->nullable();
            $table->unsignedTinyInteger('borrower_age')->nullable()->comment('Age as at period end');

            // ── Borrower contact (unstructured — users.address is one text blob) ──
            $table->text('address_raw')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // ── Account ──
            $table->string('nca_credit_type')->nullable();
            $table->string('ncr_purpose_code')->nullable();
            $table->string('loan_type')->nullable();
            $table->string('product_code')->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();

            // ── Money (as at period end where reconstructable, else null) ──
            $table->decimal('principal_amount', 15, 2)->nullable();
            $table->decimal('total_interest', 15, 2)->nullable();
            $table->decimal('total_fees', 15, 2)->nullable();
            $table->decimal('total_vat', 15, 2)->nullable();
            $table->decimal('total_amount_due', 15, 2)->nullable();
            $table->decimal('instalment_amount', 15, 2)->nullable();
            $table->decimal('opening_balance', 15, 2)->nullable()->comment('Prior period snapshot current_balance, if any');
            $table->decimal('current_balance', 15, 2)->nullable()->comment('loans.remaining_balance — populated only for recent periods (no balance history to reconstruct older months)');
            $table->decimal('arrears_amount', 15, 2)->default(0)->comment('Sum of instalments due by period end minus payments allocated oldest-first');
            $table->decimal('last_payment_amount', 15, 2)->nullable();
            $table->decimal('write_off_amount', 15, 2)->default(0);
            $table->decimal('carried_forward_shortfall', 15, 2)->default(0);
            $table->decimal('principal_at_default', 15, 2)->nullable()->comment('Contractual principal at first_default_date — s.103(5) in duplum reference');

            // ── Dates ──
            $table->date('disbursed_date')->nullable();
            $table->date('first_instalment_date')->nullable();
            $table->date('final_instalment_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->date('settled_date')->nullable();
            $table->date('written_off_date')->nullable();
            $table->date('first_default_date')->nullable()->comment('First period DPD crossed the default threshold — frozen once set');

            // ── Delinquency (all computed as at period end / write-off date) ──
            $table->unsignedInteger('days_past_due')->default(0);
            $table->unsignedSmallInteger('instalments_in_arrears')->default(0);
            $table->unsignedSmallInteger('months_in_arrears')->default(0);
            $table->unsignedTinyInteger('ifrs9_stage')->nullable()->comment('Internal risk stage — tagged internal_only, never exported to a bureau');
            $table->string('payment_status_code', 10)->nullable()->comment('KL:-prefixed interim code — NOT the SACRRA controlled vocabulary (Phase 2)');
            $table->string('payment_profile_24m', 24)->nullable()->comment('One char per month, most recent first, forward-accumulated; U = unknown history');

            // ── Status ──
            $table->string('account_status', 20)->nullable()->comment('Open | Delinquent | Closed | WrittenOff | Legal');
            $table->string('loan_status_raw', 30)->nullable();
            $table->boolean('under_dispute')->default(false);
            $table->string('dispute_reference')->nullable();

            // ── Meta ──
            $table->timestamp('snapshot_at')->nullable();
            $table->char('source_hash', 64)->nullable()->comment('Hash of the source fields — month-to-month change detection (Phase 2 deltas)');
            $table->timestamps();

            $table->unique(['period', 'generation', 'loan_id']);
            $table->index(['period', 'generation']);
            $table->index(['period', 'account_status']);
            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_account_snapshots');
    }
};
