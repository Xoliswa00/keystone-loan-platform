<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CloDecision;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LoanApplication::create() fires LoanApplicationObserver::created(), which
 * runs CloDecisionEngine::evaluate() automatically (the "LoanSubmitted" event
 * hook) — so these tests never call the engine directly, they assert on what
 * the observer produced.
 */
class CloDecisionEngineTest extends TestCase
{
    use DatabaseTransactions;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        // firstOrCreate, not firstOrFail — this test uses DatabaseTransactions
        // (not RefreshDatabase), so it doesn't control whether the DB has
        // been seeded; it shouldn't depend on another test class's
        // RefreshDatabase run happening to leave seed data behind.
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

    private function makeUser(array $overrides = []): User
    {
        // 'blacklisted' isn't mass-assignable on User — apply it separately via forceFill.
        $blacklisted = $overrides['blacklisted'] ?? null;
        unset($overrides['blacklisted']);

        $user = User::create(array_merge([
            'name' => 'Test Applicant',
            'email' => 'clo-test-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => '0821234567',
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ], $overrides));

        if ($blacklisted !== null) {
            $user->forceFill(['blacklisted' => $blacklisted])->save();
        }

        DB::table('customer_profiles')->insert([
            'user_id' => $user->id,
            'employment_tenure' => 'over_3y',
            'net_monthly_income' => 15000,
            'expense_housing' => 2000,
            'expense_transport' => 1000,
            'expense_existing_debt' => 0,
            'expense_insurance' => 500,
            'expense_living' => 2000,
            'profile_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_details')->insert([
            'user_id' => $user->id,
            'account_holder_name' => $user->name,
            'bank_name' => 'Test Bank',
            'account_number' => '1234567890',
            'account_type' => 'savings',
            'payment_method' => 'debit_order',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('popia_consents')->insert([
            'user_id' => $user->id,
            'consent_type' => 'data_processing',
            'granted' => true,
            'consented_at' => now(),
        ]);

        foreach (['id_document', 'payslip', 'bank_statement'] as $docType) {
            DB::table('customer_documents')->insert([
                'user_id' => $user->id,
                'document_type' => $docType,
                'file_path' => "documents/{$docType}.pdf",
                'verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function makeApplication(User $user, array $overrides = []): LoanApplication
    {
        return LoanApplication::create(array_merge([
            'user_id' => $user->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'pending',
            'reviewer_id' => null,
            'affordability_checked' => true,
            'affordability_disposable_income' => 9500,
            'affordability_max_instalment' => 2850,
            'affordability_instalment_requested' => 1200,
        ], $overrides));
    }

    public function test_clean_affordable_application_is_advisory_approved(): void
    {
        $user = $this->makeUser();
        $application = $this->makeApplication($user);

        $decision = CloDecision::latestFor($application->id)->firstOrFail();

        $this->assertSame('APPROVE', $decision->decision);
        $this->assertSame('PASS', $decision->compliance_status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'clo_decision',
            'auditable_type' => LoanApplication::class,
            'auditable_id' => $application->id,
        ]);
    }

    public function test_missing_documents_forces_review_with_required_actions(): void
    {
        $user = $this->makeUser();
        DB::table('customer_documents')
            ->where('user_id', $user->id)
            ->where('document_type', 'payslip')
            ->delete();

        $application = $this->makeApplication($user);

        $decision = CloDecision::latestFor($application->id)->firstOrFail();

        $this->assertSame('REVIEW', $decision->decision);
        $this->assertContains('no_supporting_documents', $decision->fraud_flags);
        $this->assertNotEmpty($decision->required_actions);
    }

    public function test_blacklisted_user_is_rejected(): void
    {
        $user = $this->makeUser(['blacklisted' => true]);
        $application = $this->makeApplication($user);

        $decision = CloDecision::latestFor($application->id)->firstOrFail();

        $this->assertSame('REJECT', $decision->decision);
        $this->assertSame('FAIL', $decision->compliance_status);
    }

    public function test_submission_creates_exactly_one_decision_and_audit_row(): void
    {
        $user = $this->makeUser();
        $application = $this->makeApplication($user);

        $this->assertSame(1, CloDecision::where('loan_application_id', $application->id)->count());
        $this->assertSame(1, AuditLog::where('event', 'clo_decision')
            ->where('auditable_id', $application->id)->count());
    }
}
