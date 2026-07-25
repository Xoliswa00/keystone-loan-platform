<?php

namespace Tests\Feature;

use App\Models\chart_of_accounts;
use App\Models\companies;
use App\Models\gl_accounts;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\PopiaConsent;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\LoanReversalService;
use Database\Seeders\ChartOfAccountsAndGlMappingsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Undo a loan approval/disbursement that turns out to have been a mistake.
 * One staff-facing action (LoanReversalService::reverseApproval()) that
 * branches internally on how far the mistake propagated — see the class
 * docblock. Neither LoanController::approve() nor DisbursementService::
 * approveAndPost() had any undo path before this.
 */
class LoanReversalServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        (new ChartOfAccountsAndGlMappingsSeeder)->run();
        $this->ensureBranchOne();
        foreach (['1100', '1200', '4100', '4200'] as $code) {
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
            'email' => 'reversal-staff-'.uniqid('', true).'@example.com',
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

    private function makeApplication(): LoanApplication
    {
        $client = User::create([
            'name' => 'Test Client',
            'email' => 'reversal-client-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        foreach (['data_processing', 'credit_bureau_check'] as $type) {
            PopiaConsent::create([
                'user_id' => $client->id,
                'consent_type' => $type,
                'granted' => true,
                'consented_at' => now(),
            ]);
        }

        \App\Models\Customer::create([
            'user_id' => $client->id,
            'customer_code' => 'CUST-'.uniqid('', true),
            'customer_type' => 'individual',
            'payment_terms' => 'debit_order',
            'credit_limit' => 5000,
            'current_balance' => 0,
        ]);

        return LoanApplication::create([
            'user_id' => $client->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'pending',
        ]);
    }

    public function test_reverse_before_disbursement_deletes_loan_and_disbursement(): void
    {
        $staff = $this->makeStaff();
        $application = $this->makeApplication();

        $this->actingAs($staff)->post(route('loans.approve', $application->id), ['approval_comments' => 'ok']);
        $this->assertSame('approved', $application->fresh()->status);
        $loan = Loan::where('loan_application_id', $application->id)->first();
        $this->assertNotNull($loan);

        app(LoanReversalService::class)->reverseApproval($application->fresh(), $staff, 'Approved the wrong applicant.');

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
        $this->assertDatabaseCount('loan_disbursements', 0);
    }

    public function test_reverse_after_disbursement_posts_gl_reversal_and_restores_balances(): void
    {
        $staff = $this->makeStaff();
        $application = $this->makeApplication();

        $this->actingAs($staff)->post(route('loans.approve', $application->id), ['approval_comments' => 'ok']);
        $loan = Loan::where('loan_application_id', $application->id)->firstOrFail();
        $disbursement = LoanDisbursement::where('loan_id', $loan->id)->firstOrFail();
        $customer = $loan->user->customer;

        app(DisbursementService::class)->approveAndPost($disbursement->id, $staff->id);

        $loan->refresh();
        $customer->refresh();
        $this->assertSame('disbursed', $loan->status);
        $balanceAfterDisbursement = (float) $customer->current_balance;
        $this->assertGreaterThan(0, $balanceAfterDisbursement);

        app(LoanReversalService::class)->reverseApproval($application->fresh(), $staff, 'Disbursed to the wrong client.');

        $loan->refresh();
        $disbursement->refresh();
        $customer->refresh();

        $this->assertSame('reversed', $loan->status);
        $this->assertEquals(0, (float) $loan->remaining_balance);
        $this->assertSame('reversed', $disbursement->status);
        $this->assertEquals(0, (float) $customer->current_balance);
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'reversed', 'auditable_type' => LoanApplication::class, 'auditable_id' => $application->id]);
    }

    /**
     * AdminController::reversalAudit() (the reports.reversals page) used to
     * only ever query loan_repayments.transaction_type='reversed' — a loan-
     * approval/disbursement reversal never writes that table (before
     * disbursement there's no LoanRepayment row at all; after, it's a
     * Loan/LoanDisbursement/GL-level reversal), so it was invisible on this
     * report and only showed up in the Audit Log.
     */
    public function test_reversal_report_surfaces_approval_reversals(): void
    {
        $staff = $this->makeStaff();
        $application = $this->makeApplication();

        $this->actingAs($staff)->post(route('loans.approve', $application->id), ['approval_comments' => 'ok']);
        $loan = Loan::where('loan_application_id', $application->id)->firstOrFail();
        $disbursement = LoanDisbursement::where('loan_id', $loan->id)->firstOrFail();
        app(DisbursementService::class)->approveAndPost($disbursement->id, $staff->id);

        app(LoanReversalService::class)->reverseApproval($application->fresh(), $staff, 'Wrong applicant, caught after disbursement.');

        $response = $this->actingAs($staff)->get(route('reports.reversals'));

        $response->assertOk();
        $response->assertSee('Wrong applicant, caught after disbursement.');
        $response->assertSee(str_pad($application->id, 6, '0', STR_PAD_LEFT));
    }

    public function test_reverse_blocked_when_repayment_exists(): void
    {
        $staff = $this->makeStaff();
        $application = $this->makeApplication();

        $this->actingAs($staff)->post(route('loans.approve', $application->id), ['approval_comments' => 'ok']);
        $loan = Loan::where('loan_application_id', $application->id)->firstOrFail();
        $disbursement = LoanDisbursement::where('loan_id', $loan->id)->firstOrFail();
        app(DisbursementService::class)->approveAndPost($disbursement->id, $staff->id);

        LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'payment_amount' => 100,
            'payment_date' => now(),
            'due_date' => now(),
            'status' => 'paid',
            'payment_method' => 'cash',
            'transaction_type' => 'manual',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('repayment history');
        app(LoanReversalService::class)->reverseApproval($application->fresh(), $staff, 'Too late.');
    }

    public function test_reverse_blocked_when_not_approved(): void
    {
        $staff = $this->makeStaff();
        $application = $this->makeApplication(); // status = pending

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not currently approved');
        app(LoanReversalService::class)->reverseApproval($application, $staff, 'Nothing to reverse.');
    }
}
