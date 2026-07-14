<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * loan_disbursements.status was a strict enum('waiting_for_approval',
     * 'approved','rejected','disbursed') — but DisbursementController::
     * release(), DisbursementService, and several AdminController report
     * queries have all been reading/writing 'released' too. Same class of
     * bug as 2026_07_09_000002_widen_loans_status_column.php: widening to
     * a plain string instead of chasing the enum with another migration
     * next time a status is added.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE loan_disbursements MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'waiting_for_approval'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loan_disbursements MODIFY COLUMN status ENUM('waiting_for_approval','approved','rejected','disbursed') NOT NULL DEFAULT 'waiting_for_approval'");
    }
};
