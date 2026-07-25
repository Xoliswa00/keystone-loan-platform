<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payment reversal never mutates the original LoanRepayment row —
     * it creates a new offsetting row (negative amounts, status='reversed')
     * so both the original and its correction stay visible in the audit
     * trail, same non-destructive pattern NuPayService::handleReversed()
     * already uses for NuPay-triggered reversals. This FK links the
     * reversal row back to what it reverses.
     */
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->foreignId('reverses_repayment_id')->nullable()->after('gl_batch_reference')
                ->constrained('loan_repayments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['reverses_repayment_id']);
            $table->dropColumn('reverses_repayment_id');
        });
    }
};
