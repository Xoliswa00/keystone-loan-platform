<?php

namespace Tests\Feature;

use App\Models\DebtRecovery;
use App\Models\FinancialPeriod;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\FinancialPeriodService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A second round of missing-lockForUpdate() fixes, found by auditing the
 * rest of the app's mutating routes for the same pattern that caused
 * LoanController::approveApplication() to have zero duplicate-submit guard
 * earlier this session: DebtRecoveryController::open()/recordPayment(),
 * FundingFacilityService::recordDrawdown/recordRepayment/accrueInterest/
 * payInterest, BadDebtProvisionService::provisionLoan(), and
 * FinancialPeriodService::closePeriod(). This file covers the two cheapest
 * to fixture (no GL account setup needed); the GL-posting ones are covered
 * by manual trace + the lock pattern itself (identical to code already
 * tested elsewhere this session).
 */
class MoreDuplicateSubmissionGuardTest extends TestCase
{
    use DatabaseTransactions;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = LoanProduct::firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Loan',
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
            ]
        );
    }

    private function makeStaff(): User
    {
        $user = User::create([
            'name' => 'Test Staff',
            'email' => 'more-guard-staff-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
        $user->forceFill(['system_role' => 'admin'])->save();

        return $user;
    }

    private function makeWrittenOffLoan(): Loan
    {
        $client = User::create([
            'name' => 'Test Client',
            'email' => 'more-guard-client-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        $application = LoanApplication::create([
            'user_id' => $client->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'disbursed',
        ]);

        return Loan::create([
            'loan_application_id' => $application->id,
            'user_id' => $client->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'approved_amount' => 1000,
            'status' => 'written_off',
            'remaining_balance' => 1000,
        ]);
    }

    public function test_opening_a_recovery_case_twice_for_the_same_loan_is_blocked(): void
    {
        $staff = $this->makeStaff();
        $loan = $this->makeWrittenOffLoan();

        $this->actingAs($staff)->post(route('admin.recovery.open'), ['loan_id' => $loan->id])
            ->assertRedirect();
        $second = $this->actingAs($staff)->post(route('admin.recovery.open'), ['loan_id' => $loan->id]);

        $second->assertRedirect();
        $this->assertTrue(session()->has('error'));
        $this->assertSame(1, DebtRecovery::where('loan_id', $loan->id)->count());
    }

    public function test_closing_an_already_closed_period_is_blocked(): void
    {
        $staff = $this->makeStaff();

        $period = FinancialPeriod::create([
            'period' => '2026-01',
            'fiscal_year' => 2026,
            'fiscal_month' => 1,
            'is_year_end' => false,
            'status' => 'open',
            'provisioning_complete' => true,
            'facility_interest_accrued' => true,
            'bank_recon_complete' => true,
            'trial_balance_generated' => true,
        ]);

        app(FinancialPeriodService::class)->closePeriod($period, $staff->id);
        $this->assertSame('closed', $period->fresh()->status);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already closed');
        app(FinancialPeriodService::class)->closePeriod($period->fresh(), $staff->id);
    }
}
