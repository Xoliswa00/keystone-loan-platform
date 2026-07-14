<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * glposts.account_id has been pointing at 'glamfs' since the original
     * migration (2025_10_04_064241_create_glposts_table.php) — but glamfs
     * is an orphaned, never-seeded, never-referenced table (a competing
     * chart-of-accounts attempt from the same day as gl_accounts). Every
     * real GL-posting code path (GLPostingService::postArBatch(),
     * DisbursementService::getGlAccountFor()) resolves and writes
     * gl_accounts IDs, which is the table actually linked to the real
     * chart_of_accounts. Every GL post has been failing its FK check
     * because of this mismatch.
     */
    public function up(): void
    {
        Schema::table('glposts', function (Blueprint $table) {
            $table->dropForeign('glposts_account_id_foreign');
            $table->foreign('account_id')->references('id')->on('gl_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('glposts', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')->references('id')->on('glamfs');
        });
    }
};
