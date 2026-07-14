<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automated content pre-check — a signal for the admin doing the manual
     * "Verify" review, never a replacement for it. See DocumentContentCheckService.
     */
    public function up(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->enum('content_check_status', ['pending', 'plausible', 'inconclusive', 'not_applicable'])
                ->default('pending')
                ->after('verified');
            $table->string('content_check_notes')->nullable()->after('content_check_status');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropColumn(['content_check_status', 'content_check_notes']);
        });
    }
};
