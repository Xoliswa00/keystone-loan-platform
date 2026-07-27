<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A repayment's principal/interest/fee amounts represent what was
 * ALLOCATED (which may be sourced from a credit drawdown, fresh cash, or
 * both) — but reversing a payment needs to know exactly how much of each
 * GL leg to swap back, including the client_credit_balance_cr entries
 * that don't show up in payment_amount at all. Without these, reversing a
 * payment that involved credit would either misbalance the reversal batch
 * or silently fail to restore the credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->decimal('credit_applied', 10, 2)->default(0)->after('fee_amount')
                ->comment('How much of this payment was covered by drawing down an existing client credit, not fresh cash');
            $table->decimal('credit_created', 10, 2)->default(0)->after('credit_applied')
                ->comment('How much NEW credit this payment created (overpayment excess)');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn(['credit_applied', 'credit_created']);
        });
    }
};
