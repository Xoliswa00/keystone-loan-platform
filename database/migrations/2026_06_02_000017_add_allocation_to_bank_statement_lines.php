<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every bank statement line must be allocated to a GL account before
 * the reconciliation can be marked complete.
 *
 * Match categories:
 *  - nu_pay_collection     → matched to Nu-Pay staging batch
 *  - loan_disbursement     → matched to loan_disbursements record
 *  - facility_drawdown     → matched to funding_facility_transactions (drawdown)
 *  - facility_repayment    → matched to funding_facility_transactions (repayment/interest)
 *  - expense               → manually allocated to a GL expense account
 *  - recovery_payment      → matched to debt_recovery_payments
 *  - inter_account         → transfer between own accounts
 *  - other                 → miscellaneous (requires description)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            // Allocation details — populated when user assigns an unmatched line
            $table->enum('match_category', [
                'nu_pay_collection',
                'loan_disbursement',
                'facility_drawdown',
                'facility_repayment',
                'expense',
                'recovery_payment',
                'inter_account',
                'other',
            ])->nullable()->after('match_status');

            // For expense allocations: which GL account to debit/credit
            $table->unsignedBigInteger('allocated_gl_account_id')->nullable()->after('match_category');
            $table->string('allocation_description')->nullable()->after('allocated_gl_account_id');
            $table->string('allocation_gl_batch_ref')->nullable()->after('allocation_description');
            $table->boolean('allocation_posted')->default(false)->after('allocation_gl_batch_ref');

            // Who allocated it and when
            $table->unsignedBigInteger('allocated_by')->nullable()->after('allocation_posted');
            $table->timestamp('allocated_at')->nullable()->after('allocated_by');

            $table->index('match_category');
            $table->index('allocation_posted');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->dropColumn([
                'match_category', 'allocated_gl_account_id', 'allocation_description',
                'allocation_gl_batch_ref', 'allocation_posted',
                'allocated_by', 'allocated_at',
            ]);
        });
    }
};
