<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NcrPurposeCodesSeeder extends Seeder
{
    /**
     * Same codes previously inserted by 2026_06_02_000009_create_audit_and_nca_tables.php's
     * up() method, now that migration only holds schema.
     */
    public function run(): void
    {
        if (DB::table('ncr_purpose_codes')->count() > 0) {
            return;
        }

        DB::table('ncr_purpose_codes')->insert([
            ['code' => 'DC', 'description' => 'Debt Consolidation', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EDU', 'description' => 'Education', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MED', 'description' => 'Medical Expenses', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'HH', 'description' => 'Household Necessities', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EMRG', 'description' => 'Emergency Expenses', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BUS', 'description' => 'Business Purposes', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'VEHI', 'description' => 'Vehicle Purchase/Repair', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OTHER', 'description' => 'Other', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
