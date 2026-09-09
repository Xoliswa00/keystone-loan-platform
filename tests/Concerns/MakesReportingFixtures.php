<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;

/**
 * Minimal real Loan graph (user + application + loan) for reporting/export
 * tests — the reporting_account_snapshots FK to loans.id means fake loan ids
 * won't insert.
 */
trait MakesReportingFixtures
{
    protected function bareLoan(array $loanOverrides = []): Loan
    {
        $uid = uniqid('', true);

        $user = User::create([
            'name' => 'Fixture '.$uid,
            'email' => "fixture-{$uid}@example.com",
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        Customer::create([
            'user_id' => $user->id,
            'customer_code' => 'CUST-'.$uid,
            'customer_type' => 'individual',
            'current_balance' => 0,
        ]);

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Fixture',
            'terms_conditions' => true,
            'status' => 'disbursed',
        ]);

        return Loan::create(array_merge([
            'loan_application_id' => $application->id,
            'user_id' => $user->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'loan_term_months' => 1,
            'approved_amount' => 1000,
            'status' => 'disbursed',
            'principal_amount' => 1000,
            'total_amount_due' => 1050,
            'remaining_balance' => 1050,
            'disbursed_date' => now()->subMonths(3)->toDateString(),
        ], $loanOverrides));
    }
}
