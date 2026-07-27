<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disclosure figure only — set once, at approval, by
 * PaymentAdjustmentService::rollShortfallIntoLoan() when the customer had
 * an outstanding shortfall from a PRIOR loan. Deliberately never folded
 * into loan_amount/principal_amount/remaining_balance: the new loan's own
 * schedule and affordability assessment stay untouched (confirmed
 * approach), this column exists purely so agreement documents, the
 * dashboard, and statements can disclose "X carried forward from a
 * previous facility" as its own line item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('carried_forward_shortfall', 10, 2)->default(0)->after('deferred_fees');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('carried_forward_shortfall');
        });
    }
};
