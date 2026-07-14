<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AdminOpsController/DebtRecoveryController have been writing and
     * filtering on 'note_type' (general/affordability/document/collections/
     * legal) since they were built, but the admin_notes migration never
     * actually added the column — every insert through those code paths
     * would fail. Adding it now to match what the code already assumes.
     */
    public function up(): void
    {
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->enum('note_type', ['general', 'affordability', 'document', 'collections', 'legal'])
                ->default('general')
                ->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->dropColumn('note_type');
        });
    }
};
