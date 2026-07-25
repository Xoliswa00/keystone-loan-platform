<?php

namespace Tests\Feature;

use App\Models\chart_of_accounts;
use App\Models\companies;
use App\Models\Customer;
use App\Models\gl_accounts;
use App\Models\import_batch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\nupay_transaction;
use App\Models\nupay_transactions_staging;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\NuPayService;
use Database\Seeders\ChartOfAccountsAndGlMappingsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NuPayService::postTransaction() used to fail on every single transaction
 * type, on every attempt: it wrote 'loan_repayment_id' to the archived
 * nupay_transactions row (and that field was always in $fillable), but the
 * column never existed on the table — so the DB::transaction() wrapping the
 * whole flow (GL entries included) rolled back at the final archive step,
 * every time. Separately, the batch-completion count included 'tracking'
 * rows (still-in-flight mandates that are never posted, by design — see the
 * 'default' throw in postTransaction()'s type dispatch), which would have
 * kept any batch containing one permanently stuck below PROCESSED. This test
 * drives a real success posting through GL and archiving to pin both fixes.
 */
class NuPayServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        (new ChartOfAccountsAndGlMappingsSeeder)->run();
        $this->ensureBranchOne();
        foreach (['1100', '1200', '5200'] as $code) {
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

        // Explicit id=1 — Loan has no branch_id column at all, so
        // NuPayService::handleSuccess()'s `$loan->branch_id ?? 1` always
        // resolves to the literal 1; gl_accounts must exist at that exact
        // branch for resolveGl() to find them.
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
            'email' => 'nupay-staff-'.uniqid('', true).'@example.com',
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

    /**
     * A client with a disbursed single-month loan and one pending
     * repayment schedule row due this month — the minimum NuPayService
     * needs to allocate and post a payment against.
     */
    private function makeClientWithDisbursedLoan(): array
    {
        $debtorId = (string) random_int(1000000000000, 9999999999999);

        $user = User::create([
            'name' => 'Test Client',
            'email' => 'nupay-client-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => $debtorId,
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
            'loan_id' => $application->id, // references loan_applications.id
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

    public function test_success_posting_archives_loan_repayment_id_and_completes_batch_excluding_tracking_rows(): void
    {
        $staff = $this->makeStaff();
        ['user' => $client] = $this->makeClientWithDisbursedLoan();

        $batch = import_batch::create([
            'source' => 'nupay',
            'original_filename' => 'test.csv',
            'stored_path' => 'imports/test.csv',
            'checksum' => uniqid('', true),
            'import_ref' => 'REF-'.uniqid('', true),
            'status' => 'CAPTURED',
        ]);

        $successTxn = nupay_transactions_staging::create([
            'import_id' => $batch->id,
            'import_ref' => $batch->import_ref,
            'transaction_type' => 'success',
            'status' => 'success',
            'mandate_id' => 'MANDATE-'.uniqid('', true),
            'mandate_request_tran_id' => 'MRTI-'.uniqid('', true),
            'debtor_id' => $client->ID_Number,
            'instalment_amount' => 1050,
            'action_date' => now()->toDateString(),
        ]);

        // Still in-flight — deliberately never posted (see the class docblock).
        $trackingTxn = nupay_transactions_staging::create([
            'import_id' => $batch->id,
            'import_ref' => $batch->import_ref,
            'transaction_type' => 'tracking',
            'status' => 'tracking',
            'mandate_id' => 'MANDATE-'.uniqid('', true),
            'debtor_id' => $client->ID_Number,
            'instalment_amount' => 0,
            'action_date' => now()->toDateString(),
        ]);

        app(NuPayService::class)->postTransaction($successTxn->id, $staff->id);

        $successTxn->refresh();
        $this->assertNotNull($successTxn->posted_at, 'Posting must succeed end-to-end, including the archive step.');

        $archived = nupay_transaction::where('import_ref', $batch->import_ref)
            ->where('mandate_id', $successTxn->mandate_id)->first();
        $this->assertNotNull($archived, 'The permanent nupay_transactions archive row must be created.');
        $this->assertNotNull($archived->loan_repayment_id, 'loan_repayment_id must be written now that the column exists.');
        $this->assertSame($successTxn->mandate_request_tran_id, $archived->mandate_request_tran_id);

        $batch->refresh();
        $this->assertSame('PROCESSED', $batch->status,
            'Batch must reach PROCESSED once its only postable transaction is posted — the still-pending tracking row must not block this.');

        $this->assertNull($trackingTxn->fresh()->posted_at);
    }
}
