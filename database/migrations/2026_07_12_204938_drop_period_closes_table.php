<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * period_closes was an orphaned side-effect of the standalone
 * `keystone:close-period` console command — nothing in the app ever read
 * from it, and financial_periods.status (via FinancialPeriod) is the real
 * period-close gate that GLPostingService::canPostToPeriod() checks. The
 * console command now delegates to FinancialPeriodService instead of
 * writing to this table, so it's no longer needed.
 *
 * NOT touching bank_statement_lines — it's created in the same original
 * migration (2026_06_02_000011_create_period_closes_table.php) but is
 * actively used by bank reconciliation (BusinessBankStatementController /
 * BankAllocationController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('period_closes');
    }

    public function down(): void
    {
        Schema::create('period_closes', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();
            $table->foreignId('closed_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
