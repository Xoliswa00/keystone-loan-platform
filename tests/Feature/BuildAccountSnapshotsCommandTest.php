<?php

namespace Tests\Feature;

use App\Models\AccountSnapshot;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BuildAccountSnapshotsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeDisbursedLoan(): Loan
    {
        $uid = uniqid('', true);
        $user = User::create([
            'name' => 'Cmd Client '.$uid,
            'email' => "cmd-{$uid}@example.com",
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
        Customer::create(['user_id' => $user->id, 'customer_code' => 'CUST-'.$uid, 'customer_type' => 'individual', 'current_balance' => 0]);
        $app = LoanApplication::create([
            'user_id' => $user->id, 'loan_type' => 'personal', 'loan_term_months' => 1,
            'loan_amount' => 1000, 'purpose' => 'x', 'terms_conditions' => true, 'status' => 'disbursed',
        ]);
        $loan = Loan::create([
            'loan_application_id' => $app->id, 'user_id' => $user->id, 'loan_type' => 'personal',
            'loan_amount' => 1000, 'interest_rate' => 5, 'loan_term' => 1, 'loan_term_months' => 1,
            'approved_amount' => 1000, 'status' => 'disbursed', 'principal_amount' => 1000,
            'total_amount_due' => 1050, 'remaining_balance' => 1050,
            'disbursed_date' => now()->subMonths(3)->toDateString(),
        ]);
        RepaymentSchedule::create([
            'loan_id' => $app->id, 'user_id' => $user->id, 'installment_number' => 1,
            'emi_amount' => 1050, 'principal_amount' => 1000, 'interest_amount' => 50, 'fee_amount' => 0,
            'due_date' => now()->subMonth()->toDateString(), 'status' => 'pending',
        ]);

        return $loan;
    }

    public function test_command_no_ops_when_snapshots_disabled(): void
    {
        config(['exports.snapshots_enabled' => false]);
        $loan = $this->makeDisbursedLoan();

        $this->artisan('keystone:build-account-snapshots', ['period' => now()->subMonthNoOverflow()->format('Y-m')])
            ->assertSuccessful();

        $this->assertSame(0, AccountSnapshot::where('loan_id', $loan->id)->count());
    }

    public function test_command_builds_snapshots_when_enabled(): void
    {
        config(['exports.snapshots_enabled' => true]);
        $loan = $this->makeDisbursedLoan();
        $period = now()->subMonthNoOverflow()->format('Y-m');

        $this->artisan('keystone:build-account-snapshots', ['period' => $period])
            ->assertSuccessful();

        $this->assertSame(1, AccountSnapshot::where('period', $period)->where('loan_id', $loan->id)->count());
    }

    public function test_command_rejects_bad_period(): void
    {
        config(['exports.snapshots_enabled' => true]);

        $this->artisan('keystone:build-account-snapshots', ['period' => 'nonsense'])
            ->assertFailed();
    }
}
