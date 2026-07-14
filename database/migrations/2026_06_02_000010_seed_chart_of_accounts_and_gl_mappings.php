<?php

use Illuminate\Database\Migrations\Migration;

/**
 * This migration originally seeded the Chart of Accounts and GL Mappings
 * directly — that data now lives in
 * database/seeders/ChartOfAccountsAndGlMappingsSeeder.php instead (data
 * seeding belongs in seeders, not migrations). Left as a no-op rather than
 * deleted: this migration's name is already recorded as run in every
 * existing environment's migrations table, and the file still marks where
 * that history point was in the schema's timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
