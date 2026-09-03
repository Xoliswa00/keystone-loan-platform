<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watermark for the Xquisite error forwarder (keystone:report-errors). A row is
 * shipped to the central monitoring hub exactly once; forwarded_at is stamped
 * when the hub acknowledges the batch. NULL = not yet forwarded.
 *
 * A column rather than a cache cursor: it survives the cache:clear that every
 * cPanel deploy runs (a wiped cursor would re-forward the whole history), and
 * it's auditable — you can see which errors have reached the hub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->timestamp('forwarded_at')->nullable()->after('logged_at');
            $table->index(['forwarded_at', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex(['forwarded_at', 'level']);
            $table->dropColumn('forwarded_at');
        });
    }
};
