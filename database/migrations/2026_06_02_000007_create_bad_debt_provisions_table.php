<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bad_debt_provisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // IFRS 9 staging at time of provisioning
            $table->unsignedTinyInteger('ifrs9_stage');   // 1 | 2 | 3
            $table->unsignedSmallInteger('days_past_due');

            // Balances at time of provisioning
            $table->decimal('outstanding_balance', 12, 2);
            $table->decimal('provision_rate', 6, 4);      // e.g. 0.05 = 5%
            $table->decimal('provision_amount', 12, 2);   // outstanding × rate

            // Prior provision for movement calculation
            $table->decimal('prior_provision', 12, 2)->default(0);
            $table->decimal('provision_movement', 12, 2)->default(0); // = current - prior

            // GL reference
            $table->string('gl_batch_reference')->nullable();
            $table->boolean('gl_posted')->default(false);

            // Who ran the provisioning and when
            $table->foreignId('created_by')->constrained('users');
            $table->date('provision_date');

            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'provision_date']);
            $table->index(['ifrs9_stage', 'provision_date']);
        });

        // ── Add missing columns to loan_repayments ──────────────────────────
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()->after('loan_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('repayment_schedule_id')
                ->nullable()->after('user_id')
                ->constrained('repayment_schedules')->nullOnDelete();

            $table->unsignedBigInteger('nupay_staging_id')->nullable()->after('repayment_schedule_id');

            $table->decimal('principal_amount', 12, 2)->default(0)->after('payment_amount');
            $table->decimal('interest_amount',  12, 2)->default(0)->after('principal_amount');
            $table->decimal('fee_amount',       12, 2)->default(0)->after('interest_amount');
            $table->decimal('nupay_fee',        12, 2)->default(0)->after('fee_amount');

            $table->string('gl_batch_reference')->nullable()->after('notes');
            $table->string('transaction_type')->nullable()->after('gl_batch_reference'); // success|failed|canceled|reversed

            $table->index('repayment_schedule_id');
        });

        // ── Add written_off fields to loans ─────────────────────────────────
        Schema::table('loans', function (Blueprint $table) {
            $table->date('written_off_date')->nullable()->after('deferred_fees');
            $table->decimal('write_off_amount', 12, 2)->default(0)->after('written_off_date');
            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('write_off_reason')->nullable()->after('written_off_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bad_debt_provisions');

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['user_id', 'repayment_schedule_id']);
            $table->dropColumn([
                'user_id', 'repayment_schedule_id', 'nupay_staging_id',
                'principal_amount', 'interest_amount', 'fee_amount', 'nupay_fee',
                'gl_batch_reference', 'transaction_type',
            ]);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['written_off_by']);
            $table->dropColumn(['written_off_date', 'write_off_amount', 'written_off_by', 'write_off_reason']);
        });
    }
};
