<?php

namespace Database\Seeders;

use App\Models\Loan_fee_rules;
use Illuminate\Database\Seeder;

class LoanFeeRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Loan_fee_rules::insert([
            [
                'fee_type' => 'interest',
                'applicability' => 'first_timer',
                'rate' => 0.05,
                'flat_fee' => null,
                'cap' => null,
                'months_valid' => null,
            ],
            [
                'fee_type' => 'interest',
                'applicability' => 'loyal_returnee',
                'rate' => 0.03,
                'flat_fee' => null,
                'cap' => null,
                'months_valid' => 12,
            ],
            [
                'fee_type' => 'initiation',
                'applicability' => 'general',
                'rate' => 0.10,
                'flat_fee' => 165.00,
                'cap' => 1050.00,
                'months_valid' => null,

            ],
            [
                'fee_type' => 'service',
                'applicability' => 'general',
                'flat_fee' => 60.00,
                'rate' => 0.05,
                'cap' => null,
                'months_valid' => null,

            ],
            [
                'fee_type' => 'default',
                'applicability' => 'general',
                'rate' => 0.05,
                'flat_fee' => null,
                'cap' => null,
                'months_valid' => null,

            ],
        ]);
    }
}
