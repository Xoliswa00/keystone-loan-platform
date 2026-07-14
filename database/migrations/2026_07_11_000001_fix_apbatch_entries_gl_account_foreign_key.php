<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same bug as glposts.account_id (fixed in
     * 2026_07_10_000001_fix_glposts_account_id_foreign_key.php) —
     * apbatch_entries.gl_account_id points at the orphaned 'glamfs' table
     * instead of the real gl_accounts. The AP-batch controllers that would
     * write here are currently deleted from the app, so this is dormant
     * rather than actively crashing anything — fixing it now so it isn't a
     * landmine if AP posting is ever reinstated.
     */
    public function up(): void
    {
        Schema::table('apbatch_entries', function (Blueprint $table) {
            $table->dropForeign('apbatch_entries_gl_account_id_foreign');
            $table->foreign('gl_account_id')->references('id')->on('gl_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('apbatch_entries', function (Blueprint $table) {
            $table->dropForeign(['gl_account_id']);
            $table->foreign('gl_account_id')->references('id')->on('glamfs');
        });
    }
};
