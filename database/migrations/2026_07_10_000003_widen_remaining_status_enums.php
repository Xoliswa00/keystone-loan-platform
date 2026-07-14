<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same bug class as 2026_07_09_000002 (loans.status) and
     * 2026_07_10_000002 (loan_disbursements.status) — these three enums
     * were missed by that cleanup pass:
     *
     *  - loan_applications.status: missing 'under_review'
     *    (AdminOpsController::assign())
     *  - loan_repayments.status: missing 'payment_failed', 'reversed'
     *    (NuPayService::handleFailed()/handleReversed())
     *  - repayment_schedules.status: missing 'payment_failed'
     *    (NuPayService::handleFailed())
     *
     * All three would throw/truncate at the DB layer the moment those code
     * paths actually ran — i.e. the first NuPay dishonour or reversal, or
     * the first reviewer assignment.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE loan_applications MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE loan_repayments MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE repayment_schedules MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loan_applications MODIFY COLUMN status ENUM('pending','approved','rejected','deleted') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE loan_repayments MODIFY COLUMN status ENUM('paid','pending','overdue') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE repayment_schedules MODIFY COLUMN status ENUM('pending','paid','overdue','rejected') NOT NULL DEFAULT 'pending'");
    }
};
