<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            // Bank statement analysis results — populated by BankStatementAnalysisService
            $table->unsignedSmallInteger('avg_days_to_zero')->nullable()
                ->comment('Average days after payday until balance drops below R500');
            $table->unsignedSmallInteger('avg_days_to_low')->nullable()
                ->comment('Average days after payday until balance drops below 20% of salary');
            $table->decimal('avg_daily_spend_post_payday', 10, 2)->nullable()
                ->comment('Average daily spend in the 7 days after payday');
            $table->decimal('detected_regular_debits', 10, 2)->nullable()
                ->comment('Sum of detected regular monthly debit orders');
            $table->unsignedSmallInteger('detected_debit_count')->nullable()
                ->comment('Number of regular debit orders detected');
            $table->json('detected_debit_orders')->nullable()
                ->comment('JSON array of detected debit orders: [{description, amount, day_of_month}]');
            $table->decimal('verified_income_amount', 12, 2)->nullable()
                ->comment('Largest consistent monthly credit — verified salary from bank statement');
            $table->string('verified_income_date_pattern')->nullable()
                ->comment('Day of month salary typically credits, e.g. "25"');
            $table->enum('bank_statement_risk_flag', ['low', 'medium', 'high', 'very_high'])->nullable()
                ->comment('Risk flag based on burn rate analysis');
            $table->text('bank_statement_risk_reason')->nullable();
            $table->timestamp('bank_analysis_run_at')->nullable();
        });

        // Allow multiple bank statements per user (soft-deletes old ones on re-upload)
        // customer_documents already has user_id + document_type index
        // We just need to track which are for the bank analysis
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('document_date');
            $table->string('account_number_last4')->nullable()->after('bank_name');
            $table->date('statement_from')->nullable()->after('account_number_last4');
            $table->date('statement_to')->nullable()->after('statement_from');
            $table->boolean('analysis_complete')->default(false)->after('statement_to');
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'avg_days_to_zero', 'avg_days_to_low', 'avg_daily_spend_post_payday',
                'detected_regular_debits', 'detected_debit_count', 'detected_debit_orders',
                'verified_income_amount', 'verified_income_date_pattern',
                'bank_statement_risk_flag', 'bank_statement_risk_reason', 'bank_analysis_run_at',
            ]);
        });
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number_last4', 'statement_from', 'statement_to', 'analysis_complete']);
        });
    }
};
