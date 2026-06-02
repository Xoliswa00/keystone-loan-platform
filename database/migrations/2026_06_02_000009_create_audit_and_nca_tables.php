<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Audit log — immutable record of every state change ───────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');                            // created|updated|deleted|approved|rejected|disbursed|settled
            $table->string('auditable_type');                   // Loan|LoanApplication|LoanDisbursement|User
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();     // no updated_at — immutable

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });

        // ── Loan status history — per-loan state transition log ──────────────
        Schema::create('loan_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('loan_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'created_at']);
        });

        // ── NCA agreements — pre-agreement statement tracking ────────────────
        Schema::create('nca_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('reference')->unique();              // e.g. NCA-2026-000123
            $table->enum('document_type', [
                'pre_agreement_statement',   // NCA s.92
                'loan_agreement',            // NCA s.93
                'settlement_quote',          // NCA s.125
            ]);

            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();           // when delivered to client
            $table->timestamp('accepted_at')->nullable();       // when client signed/accepted
            $table->string('acceptance_method')->nullable();    // digital | physical | sms

            $table->decimal('principal_amount', 12, 2);
            $table->decimal('initiation_fee', 12, 2)->default(0);
            $table->decimal('initiation_fee_vat', 12, 2)->default(0);
            $table->decimal('monthly_service_fee', 12, 2)->default(0);
            $table->decimal('monthly_service_fee_vat', 12, 2)->default(0);
            $table->decimal('monthly_interest', 12, 2)->default(0);
            $table->decimal('total_cost_of_credit', 12, 2)->default(0);  // s.102 prescribed disclosure
            $table->decimal('annual_percentage_rate', 8, 4)->default(0); // APR for disclosure

            $table->integer('term_months')->default(1);
            $table->date('first_payment_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_application_id', 'document_type']);
        });

        // ── NCR purpose codes — reference table ──────────────────────────────
        Schema::create('ncr_purpose_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ── Early settlements ─────────────────────────────────────────────────
        Schema::create('early_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->date('settlement_date');
            $table->decimal('outstanding_balance', 12, 2);
            $table->decimal('interest_rebate', 12, 2)->default(0);  // NCA s.125 rebate
            $table->decimal('fee_rebate', 12, 2)->default(0);
            $table->decimal('settlement_amount', 12, 2);            // what client actually pays

            $table->enum('status', ['quoted', 'accepted', 'paid', 'cancelled'])->default('quoted');
            $table->timestamp('quote_expires_at')->nullable();       // quote valid for X days
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Reviewer scorecard — computed from real data (replaces missing view) ──
        Schema::create('reviewer_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->string('reviewer_name');
            $table->year('year');
            $table->unsignedTinyInteger('month');

            $table->unsignedInteger('applications_reviewed')->default(0);
            $table->unsignedInteger('applications_approved')->default(0);
            $table->unsignedInteger('applications_rejected')->default(0);
            $table->decimal('approval_rate', 5, 2)->default(0);
            $table->decimal('total_disbursed', 12, 2)->default(0);
            $table->decimal('total_outstanding', 12, 2)->default(0);
            $table->decimal('total_in_default', 12, 2)->default(0);
            $table->decimal('default_rate', 5, 2)->default(0);
            $table->decimal('risky_exposure', 12, 2)->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['reviewer_id', 'year', 'month']);
        });

        // ── POPIA consent log ─────────────────────────────────────────────────
        Schema::create('popia_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('consent_type', [
                'data_processing',
                'credit_bureau_check',
                'marketing',
                'third_party_sharing',
            ]);
            $table->boolean('granted');
            $table->string('ip_address', 45)->nullable();
            $table->text('context')->nullable();           // e.g. "Registration form v2"
            $table->timestamp('consented_at')->useCurrent();

            $table->index(['user_id', 'consent_type']);
        });

        // Seed NCR purpose codes
        \Illuminate\Support\Facades\DB::table('ncr_purpose_codes')->insert([
            ['code' => 'DC',    'description' => 'Debt Consolidation',     'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EDU',   'description' => 'Education',              'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MED',   'description' => 'Medical Expenses',       'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'HH',    'description' => 'Household Necessities',  'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EMRG',  'description' => 'Emergency Expenses',     'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BUS',   'description' => 'Business Purposes',      'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'VEHI',  'description' => 'Vehicle Purchase/Repair','active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OTHER', 'description' => 'Other',                  'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('popia_consents');
        Schema::dropIfExists('reviewer_scorecards');
        Schema::dropIfExists('early_settlements');
        Schema::dropIfExists('ncr_purpose_codes');
        Schema::dropIfExists('nca_agreements');
        Schema::dropIfExists('loan_status_history');
        Schema::dropIfExists('audit_logs');
    }
};
