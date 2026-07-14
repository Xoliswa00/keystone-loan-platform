<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_periods', function (Blueprint $table) {
            $table->boolean('bank_recon_no_activity')->default(false)->after('bank_recon_complete')
                ->comment('True when bank recon was satisfied by a verified "no activity" attestation instead of an actual reconciled bank statement batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_periods', function (Blueprint $table) {
            $table->dropColumn('bank_recon_no_activity');
        });
    }
};
