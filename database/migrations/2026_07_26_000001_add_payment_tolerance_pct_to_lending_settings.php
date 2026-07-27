<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much a payment can miss the instalment total (either direction)
 * before ManualPaymentService/NuPayService reject it outright — a mandate
 * typo or a slightly-short proof of payment shouldn't hard-fail the same
 * way a genuinely wrong amount should. Staff-editable, same reasoning as
 * every other policy knob on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lending_settings', function (Blueprint $table) {
            $table->decimal('payment_tolerance_pct', 4, 2)->default(0.05)->after('affordability_ratio')
                ->comment('Max variance (as a fraction of the instalment total) a payment can be short or over by and still post, instead of being rejected');
        });
    }

    public function down(): void
    {
        Schema::table('lending_settings', function (Blueprint $table) {
            $table->dropColumn('payment_tolerance_pct');
        });
    }
};
