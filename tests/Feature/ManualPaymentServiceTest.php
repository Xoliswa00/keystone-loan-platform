<?php

namespace Tests\Feature;

use App\Models\chart_of_accounts;
use App\Models\companies;
use App\Models\Customer;
use App\Models\gl_accounts;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\ManualPaymentService;
use Database\Seeders\ChartOfAccountsAndGlMappingsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payments that don't come through NuPay — a client uploading proof of an
 * EFT/cash payment, or staff recording one directly. Client submissions
 * (submitForReview) must not move any money until staff verify them;
 * verification/staff-direct-entry (recordAndPost) is the only place that
 * actually posts to GL — before this, LoanRepaymentController::store()
 * mutated loans.remaining_balance/customers.current_balance directly with
 * no real GL batch, despite claiming to "post to GL."
 */
class ManualPaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        (new ChartOfAccountsAndGlMappingsSeeder)->run();
        $this->ensureBranchOne();
        foreach (['1100', '1200'] as $code) {
            $this->provisionGlAccount($code, 1);
        }

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

    private function ensureBranchOne(): void
    {
        if (DB::table('branches')->where('id', 1)->exists()) {
            return;
        }

        $company = companies::first() ?? companies::create(['name' => 'Test Company']);

        DB::table('branches')->insert([
            'id' => 1,
            'branch_code' => 'HQ-TEST',
            'branch_name' => 'Head Office',
            'company_id' => $company->id,
            'branch_type' => 'online',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function provisionGlAccount(string $accountCode, int $branchId): void
    {
        $coa = chart_of_accounts::where('account_code', $accountCode)->firstOrFail();

        gl_accounts::firstOrCreate(
            ['chart_of_account_id' => $coa->id, 'branch_id' => $branchId],
            ['full_account_no' => $accountCode.'-'.$branchId, 'opening_balance' => 0, 'current_balance' => 0]
        );
    }

    private function makeStaff(): User
    {
        $user = User::create([
            'name' => 'Test Staff',
            'email' => 'manual-pay-staff-'.uniqid('', true).'@example.com',
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

    /** A client with a disbursed single-month loan and one pending schedule row. */
    private function makeClientWithDisbursedLoan(): array
    {
        $user = User::create([
            'name' => 'Test Client',
            'email' => 'manual-pay-client-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_code' => 'CUST-'.uniqid('', true),
            'customer_type' => 'individual',
            'payment_terms' => 'debit_order',
            'credit_limit' => 5000,
            'current_balance' => 1050,
        ]);

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'disbursed',
        ]);

        $loan = Loan::create([
            'loan_application_id' => $application->id,
            'user_id' => $user->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'loan_term_months' => 1,
            'approved_amount' => 1000,
            'status' => 'disbursed',
            'remaining_balance' => 1050,
            'deferred_interest' => 0,
            'deferred_fees' => 0,
        ]);

        $schedule = RepaymentSchedule::create([
            'loan_id' => $application->id,
            'user_id' => $user->id,
            'installment_number' => 1,
            'emi_amount' => 1050,
            'principal_amount' => 1000,
            'interest_amount' => 50,
            'fee_amount' => 0,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        return compact('user', 'customer', 'loan', 'schedule');
    }

    public function test_client_submission_creates_no_balance_or_gl_change(): void
    {
        ['user' => $client, 'loan' => $loan, 'customer' => $customer, 'schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $repayment = app(ManualPaymentService::class)->submitForReview($schedule, $client, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'payment_reference' => 'EFT-TEST-1',
            'proof_of_payment_path' => 'proof_of_payment/test.pdf',
        ]);

        $this->assertSame('pending_review', $repayment->status);
        $this->assertSame($client->id, $repayment->submitted_by);
        $this->assertNull($repayment->gl_batch_reference);
        $this->assertSame('pending', $schedule->fresh()->status);
        $this->assertEquals(1050, (float) $loan->fresh()->remaining_balance);
        $this->assertEquals(1050, (float) $customer->fresh()->current_balance);
        $this->assertDatabaseCount('glbatches', 0);
    }

    public function test_staff_direct_entry_posts_immediately_to_gl(): void
    {
        $staff = $this->makeStaff();
        ['loan' => $loan, 'customer' => $customer, 'schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $repayment = app(ManualPaymentService::class)->recordAndPost($schedule, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_reference' => 'CASH-TEST-1',
        ], $staff);

        $this->assertSame('paid', $repayment->status);
        $this->assertNotNull($repayment->gl_batch_reference);
        $this->assertSame($staff->id, $repayment->verified_by);
        $this->assertSame('paid', $schedule->fresh()->status);
        $this->assertEquals(0, (float) $loan->fresh()->remaining_balance);
        $this->assertEquals(0, (float) $customer->fresh()->current_balance);
        // gl_batch_reference stores the AR batch's own reference (same
        // convention as NuPayService), not the glbatches row's auto-generated one.
        $this->assertDatabaseHas('arbatches', [
            'reference' => $repayment->gl_batch_reference,
            'status' => 'posted',
            'posted_to_gl' => true,
        ]);
        $this->assertDatabaseCount('glbatches', 1);
    }

    public function test_reversing_a_posted_payment_restores_balances_and_reopens_schedule(): void
    {
        $staff = $this->makeStaff();
        ['loan' => $loan, 'customer' => $customer, 'schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $original = app(ManualPaymentService::class)->recordAndPost($schedule, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $staff);

        $reversal = app(ManualPaymentService::class)->reversePayment($original, $staff, 'Posted against the wrong client.');

        $this->assertSame('reversed', $reversal->status);
        $this->assertEquals(-1050, (float) $reversal->payment_amount);
        $this->assertSame($original->id, $reversal->reverses_repayment_id);
        $this->assertSame('paid', $original->fresh()->status, 'The original row must never be mutated.');
        $this->assertSame('pending', $schedule->fresh()->status);
        $this->assertNull($schedule->fresh()->paid_at);
        $this->assertEquals(1050, (float) $loan->fresh()->remaining_balance);
        $this->assertEquals(1050, (float) $customer->fresh()->current_balance);
        $this->assertDatabaseCount('glbatches', 2); // original posting + reversal
    }

    public function test_reversing_an_already_reversed_payment_throws(): void
    {
        $staff = $this->makeStaff();
        ['schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $original = app(ManualPaymentService::class)->recordAndPost($schedule, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $staff);

        app(ManualPaymentService::class)->reversePayment($original, $staff, 'First reversal.');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already been reversed');
        app(ManualPaymentService::class)->reversePayment($original, $staff, 'Second reversal.');
    }

    public function test_approving_pending_claim_posts_and_marks_paid(): void
    {
        $staff = $this->makeStaff();
        ['user' => $client, 'loan' => $loan, 'customer' => $customer, 'schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $claim = app(ManualPaymentService::class)->submitForReview($schedule, $client, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'proof_of_payment_path' => 'proof_of_payment/test.pdf',
        ]);

        $posted = app(ManualPaymentService::class)->recordAndPost($schedule, [
            'payment_amount' => (float) $claim->payment_amount,
            'payment_date' => $claim->payment_date,
            'payment_method' => $claim->payment_method,
            'payment_reference' => $claim->payment_reference,
        ], $staff, $claim);

        $this->assertSame($claim->id, $posted->id, 'Approval must finalise the existing claim row, not create a new one.');
        $this->assertSame('paid', $posted->status);
        $this->assertNotNull($posted->gl_batch_reference);
        $this->assertSame('paid', $schedule->fresh()->status);
        $this->assertEquals(0, (float) $loan->fresh()->remaining_balance);
        $this->assertEquals(0, (float) $customer->fresh()->current_balance);
        $this->assertDatabaseCount('loan_repayments', 1);
    }

    public function test_rejecting_pending_claim_leaves_everything_untouched(): void
    {
        $staff = $this->makeStaff();
        ['user' => $client, 'loan' => $loan, 'customer' => $customer, 'schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $claim = app(ManualPaymentService::class)->submitForReview($schedule, $client, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'proof_of_payment_path' => 'proof_of_payment/test.pdf',
        ]);

        app(ManualPaymentService::class)->reject($claim, $staff, 'Amount does not match bank statement.');

        $claim->refresh();
        $this->assertSame('rejected', $claim->status);
        $this->assertSame($staff->id, $claim->verified_by);
        $this->assertSame('Amount does not match bank statement.', $claim->rejection_reason);
        $this->assertSame('pending', $schedule->fresh()->status);
        $this->assertEquals(1050, (float) $loan->fresh()->remaining_balance);
        $this->assertEquals(1050, (float) $customer->fresh()->current_balance);
    }

    public function test_rejecting_an_already_resolved_claim_throws(): void
    {
        $staff = $this->makeStaff();
        ['user' => $client, 'schedule' => $schedule] = $this->makeClientWithDisbursedLoan();

        $claim = app(ManualPaymentService::class)->submitForReview($schedule, $client, [
            'payment_amount' => 1050,
            'payment_date' => now()->toDateString(),
            'proof_of_payment_path' => 'proof_of_payment/test.pdf',
        ]);

        app(ManualPaymentService::class)->reject($claim, $staff, 'First rejection.');

        $this->expectException(\Exception::class);
        app(ManualPaymentService::class)->reject($claim, $staff, 'Second rejection.');
    }
}
