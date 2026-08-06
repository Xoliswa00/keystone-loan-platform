<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same bug class as the status-enum fixes — loan_repayments.payment_method
     * was enum('bank_transfer','cash','card','mobile_payment'), but
     * NuPayService writes 'nupay' on every successful/failed/reversed
     * transaction (app/Services/NuPayService.php:248,315,415). Every NuPay
     * payment has been failing at the DB layer.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE loan_repayments MODIFY COLUMN payment_method VARCHAR(20) NOT NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loan_repayments MODIFY COLUMN payment_method ENUM('bank_transfer','cash','card','mobile_payment') NOT NULL");
    }
};
