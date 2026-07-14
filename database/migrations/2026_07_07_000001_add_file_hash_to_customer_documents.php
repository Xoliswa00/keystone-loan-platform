<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SHA-256 of the uploaded file's raw bytes — lets the CLO decision engine
     * catch the same source document (e.g. one bank statement) being reused
     * across different applicant profiles, which plain filename/mime checks
     * can't see.
     */
    public function up(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('file_path');
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropIndex(['file_hash']);
            $table->dropColumn('file_hash');
        });
    }
};
