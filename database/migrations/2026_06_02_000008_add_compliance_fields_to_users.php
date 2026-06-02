<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Users: FICA / POPIA / NCR compliance fields ─────────────────────
        Schema::table('users', function (Blueprint $table) {
            // Derived from SA ID but stored for direct query performance
            $table->date('date_of_birth')->nullable()->after('ID_Number');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->string('nationality', 3)->default('ZAF')->after('gender'); // ISO 3166-1 alpha-3

            // POPIA Section 11 — explicit consent before processing personal data
            $table->boolean('popia_consent')->default(false)->after('nationality');
            $table->timestamp('popia_consent_at')->nullable()->after('popia_consent');

            // FICA — Know Your Customer verification
            $table->boolean('fica_verified')->default(false)->after('popia_consent_at');
            $table->timestamp('fica_verified_at')->nullable()->after('fica_verified');
            $table->foreignId('fica_verified_by')->nullable()->after('fica_verified_at')
                ->constrained('users')->nullOnDelete();

            // NCR — adverse listing
            $table->boolean('blacklisted')->default(false)->after('fica_verified_by');
            $table->string('blacklisted_reason')->nullable()->after('blacklisted');
            $table->timestamp('blacklisted_at')->nullable()->after('blacklisted_reason');

            // Credit bureau consent (NCA s.81)
            $table->boolean('ncr_consent')->default(false)->after('blacklisted_at');
            $table->timestamp('ncr_consent_at')->nullable()->after('ncr_consent');

            // Make ID_Number unique — fix the TODO in the original migration
            $table->unique('ID_Number', 'users_id_number_unique');
        });

        // ── Loan Applications: NCA compliance fields ─────────────────────────
        Schema::table('loan_applications', function (Blueprint $table) {
            // Fix: reviewer_id must be nullable (client submits before admin reviews)
            // Note: cannot change nullability with just ->change() on constrained FK in all DB versions
            // We drop and re-add as nullable
            $table->dropForeign(['reviewer_id']);
            $table->foreignId('reviewer_id')->nullable()->change();
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();

            // NCA Section classification
            $table->enum('nca_credit_type', [
                'small_agreement',       // ≤ R15,000, ≤ 6 months
                'intermediate_agreement',// ≤ R250,000
                'large_agreement',       // > R250,000
                'developmental',
                'incidental',
            ])->nullable()->after('loan_type');

            // Pre-agreement statement (NCA s.92)
            $table->string('nca_agreement_ref')->nullable()->after('nca_credit_type');
            $table->timestamp('nca_agreement_sent_at')->nullable()->after('nca_agreement_ref');
            $table->timestamp('nca_agreement_accepted_at')->nullable()->after('nca_agreement_sent_at');

            // Right of rescission (NCA s.121 — 5 business days)
            $table->timestamp('cooling_off_expires_at')->nullable()->after('nca_agreement_accepted_at');
            $table->boolean('rescinded')->default(false)->after('cooling_off_expires_at');
            $table->timestamp('rescinded_at')->nullable()->after('rescinded');

            // Affordability audit trail (NCA s.81 — immutable snapshot)
            $table->decimal('affordability_dti_ratio', 5, 2)->nullable()
                ->comment('Debt-to-income ratio at time of application — NCA s.81 audit trail');
            $table->timestamp('credit_bureau_checked_at')->nullable();
            $table->integer('credit_bureau_score')->nullable();
            $table->string('credit_bureau_provider')->nullable(); // TransUnion | Experian | XDS

            // NCR reporting fields
            $table->string('ncr_purpose_code')->nullable()
                ->comment('NCR prescribed loan purpose code');
            $table->string('rejection_code')->nullable()
                ->comment('NCR standardised rejection reason code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_id_number_unique');
            $table->dropForeign(['fica_verified_by']);
            $table->dropColumn([
                'date_of_birth', 'gender', 'nationality',
                'popia_consent', 'popia_consent_at',
                'fica_verified', 'fica_verified_at', 'fica_verified_by',
                'blacklisted', 'blacklisted_reason', 'blacklisted_at',
                'ncr_consent', 'ncr_consent_at',
            ]);
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'nca_credit_type', 'nca_agreement_ref',
                'nca_agreement_sent_at', 'nca_agreement_accepted_at',
                'cooling_off_expires_at', 'rescinded', 'rescinded_at',
                'affordability_dti_ratio', 'credit_bureau_checked_at',
                'credit_bureau_score', 'credit_bureau_provider',
                'ncr_purpose_code', 'rejection_code',
            ]);
        });
    }
};
