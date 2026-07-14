<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductsSeeder extends Seeder
{
    /**
     * Same products previously inserted by 2026_06_02_000001_create_loan_products_table.php's
     * up() method, now that migration only holds schema — but with
     * initiation_fee_flat/initiation_fee_cap/monthly_service_fee corrected to
     * the NCA-compliant values (150/1050/60). The original migration's seed
     * data (165/1207.50/69) was itself over the caps in config/nca.php — a
     * live bug fixed on the real dev DB earlier this session; seeding the
     * old values here would silently reintroduce it on a fresh install.
     */
    public function run(): void
    {
        if (LoanProduct::count() > 0) {
            return;
        }

        LoanProduct::create([
            'name' => 'Standard Loan',
            'code' => 'standard',
            'min_amount' => 500.00,
            'max_amount' => 3000.00,
            'min_months' => 1,
            'max_months' => 1,
            'monthly_interest_rate' => 0.0500,
            'initiation_fee_flat' => 150.00,
            'initiation_fee_rate' => 0.10,
            'initiation_fee_cap' => 1050.00,
            'monthly_service_fee' => 60.00,
            'vat_rate' => 0.15,
            'requires_enhanced_affordability' => false,
            'active' => true,
            'description' => 'Short-term loan payable on next salary date. NCA compliant.',
        ]);

        LoanProduct::create([
            'name' => 'Extended Loan',
            'code' => 'extended',
            'min_amount' => 1000.00,
            'max_amount' => 5000.00,
            'min_months' => 2,
            'max_months' => 3,
            'monthly_interest_rate' => 0.0500,
            'initiation_fee_flat' => 150.00,
            'initiation_fee_rate' => 0.10,
            'initiation_fee_cap' => 1050.00,
            'monthly_service_fee' => 60.00,
            'vat_rate' => 0.15,
            'requires_enhanced_affordability' => true,
            'active' => false,
            'description' => 'Multi-month loan up to 3 instalments. Enhanced affordability check required.',
        ]);
    }
}
