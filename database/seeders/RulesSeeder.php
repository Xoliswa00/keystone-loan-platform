<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RulesSeeder extends Seeder
{
    /**
     * Same data previously inserted by 2025_07_11_190552_create_rules_table.php's
     * up() method, now that migration only holds schema.
     */
    public function run(): void
    {
        if (DB::table('rules')->count() > 0) {
            return;
        }

        DB::table('rules')->insert([
            ['name' => 'owner', 'description' => 'Full access to all modules'],
            ['name' => 'admin', 'description' => 'System administrator with high-level permissions'],
            ['name' => 'approver', 'description' => 'Can approve or reject loan applications'],
            ['name' => 'finance_clerk', 'description' => 'Handles recon, repayments, disbursements'],
            ['name' => 'staff', 'description' => 'General staff access with limited permissions'],
        ]);
    }
}
