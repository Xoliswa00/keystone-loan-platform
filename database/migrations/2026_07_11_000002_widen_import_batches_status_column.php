<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same bug class as the other status-enum widenings in this cleanup
     * pass. import_batches.status was a strict enum('UPLOADED','CAPTURED',
     * 'FAILED_CAPTURE','READY_FOR_VALIDATION','VALIDATED','FAILED_VALIDATION',
     * 'PROCESSED'), but ImportService::import()'s catch block writes 'FAILED'
     * (app/Services/ImportServices.php:65) and
     * BankAllocationController::markComplete() writes 'RECONCILED'
     * (app/Http/Controllers/BankAllocationController.php:209) — neither
     * value fits the enum, so both would throw at the DB layer.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE import_batches MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'UPLOADED'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE import_batches MODIFY COLUMN status ENUM('UPLOADED','CAPTURED','FAILED_CAPTURE','READY_FOR_VALIDATION','VALIDATED','FAILED_VALIDATION','PROCESSED') NOT NULL DEFAULT 'UPLOADED'");
    }
};
