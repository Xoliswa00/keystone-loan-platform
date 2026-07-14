<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * loans.status has been a strict enum('pending','approved','rejected',
     * 'disbursed') since the original migration — but NuPayService::
     * reconcile(), LoanRepaymentController::updatePayment(), and
     * Loan::isSettled() have all been trying to write/read 'settled' since
     * they were built, and CustomerLimitationService/several report
     * controllers also filter on 'archived'. Neither value fits the enum,
     * so every attempt to actually mark a fully-repaid loan 'settled' has
     * been failing at the DB layer.
     *
     * 2026_06_02_000006_add_product_fields_to_loans.php already noted the
     * intent ("Change existing enum to string so we can add new statuses
     * without migrations") but never actually implemented it — doing that
     * now, via raw SQL since doctrine/dbal (needed for ->change()) isn't
     * installed.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','approved','rejected','disbursed') NOT NULL DEFAULT 'pending'");
    }
};
