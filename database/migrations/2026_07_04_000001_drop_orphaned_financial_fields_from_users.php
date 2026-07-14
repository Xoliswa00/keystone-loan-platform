<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These four columns were collected at registration but never read by
 * AffordabilityService/CloDecisionEngine — the real, actually-used
 * financial profile lives on customer_profiles (employment_type,
 * employment_tenure, net_monthly_income, itemised expenses, etc.), which
 * has no sync/reconciliation with these users columns. Keeping both
 * around invites drift between "what the user typed at signup" and
 * "what the affordability engine actually scored against". salary_payment_day
 * stays — it's genuinely used to schedule repayment dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employment_status', 'net_salary', 'salary_frequency', 'credit_score']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('employment_status', ['Full-time', 'Part-time', 'Self-employed', 'Unemployed'])->nullable();
            $table->decimal('net_salary', 10, 2)->nullable();
            $table->enum('salary_frequency', ['Weekly', 'Bi-weekly', 'Monthly'])->nullable();
            $table->integer('credit_score')->nullable();
        });
    }
};
