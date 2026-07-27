<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns for in-tolerance partial payments. Deliberately NOT a
 * new `status` value — RepaymentSchedule::daysOverdue()/ifrs9Stage() and
 * every scope (scopePending/scopeOverdue/scopeDpd) are entirely
 * status-gated, so a partially-paid schedule staying 'pending' keeps aging
 * correctly through all of that with zero changes. These two columns exist
 * only so staff/client UI can distinguish "never touched" pending from
 * "partially paid, in-tolerance" pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->decimal('amount_paid_to_date', 10, 2)->default(0)->after('emi_amount');
            $table->boolean('partial_payment_flag')->default(false)->after('amount_paid_to_date');
        });
    }

    public function down(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropColumn(['amount_paid_to_date', 'partial_payment_flag']);
        });
    }
};
