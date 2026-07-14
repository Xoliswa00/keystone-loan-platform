<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for columns filtered constantly on the admin dashboard/portfolio
 * endpoints (AdminApiController, AdminController) that were previously
 * relying on full table scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->index('status');
            $table->index('disbursed_date');
        });

        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['disbursed_date']);
        });

        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
        });
    }
};
