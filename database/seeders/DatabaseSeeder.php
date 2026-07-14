<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // ChartOfAccountsSeeder and GlmappingSeeder used to exist here too,
        // but they seeded a legacy, incompatible chart-of-accounts numbering
        // scheme (overlapping account codes with different meanings) that
        // conflicted with the real, NCA-compliant chart of accounts and GL
        // mappings — the one GLPostingService and every glmappings lookup
        // in the app actually uses. That data now lives in
        // ChartOfAccountsAndGlMappingsSeeder below; do not resurrect the old
        // classes.
        //
        // Order matters: CompaniesSeeder and ChartOfAccountsAndGlMappingsSeeder
        // must both run before BranchesSeeder — branches.company_id is a real
        // FK, and creating a branch fires the CreatedBranch observer, which
        // creates a gl_accounts row per existing chart_of_accounts entry.
        $this->call([
            CompaniesSeeder::class,
            ChartOfAccountsAndGlMappingsSeeder::class,
            BranchesSeeder::class,
            RulesSeeder::class,
            LoanProductsSeeder::class,
            NcrPurposeCodesSeeder::class,
            LendingSettingsSeeder::class,
            FinancialPeriodsSeeder::class,
            AdminSeeder::class,
            LoanFeeRulesSeeder::class,
        ]);
    }
}
