<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanProduct;
use App\Models\PopiaConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A handful of money-moving actions had no idempotency guard at all —
 * notably LoanController::approveApplication()/rejectApplication(), which
 * had neither a lockForUpdate() nor a status pre-check, so two
 * near-simultaneous requests for the same application would each create
 * their own Loan + LoanDisbursement row. These tests drive the same action
 * twice in sequence (a real race needs two DB connections to reproduce
 * exactly, but the *symptom* — the second call succeeding and mutating
 * state again — is exactly what the added lockForUpdate()+status-check
 * pair prevents, and is what these assert against).
 */
class DuplicateSubmissionGuardTest extends TestCase
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

    private function makeUser(bool $asAdmin = false): User
    {
        $user = User::create([
            'name' => $asAdmin ? 'Test Admin' : 'Test Client',
            'email' => 'dup-guard-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        if ($asAdmin) {
            $user->forceFill(['system_role' => 'admin'])->save();
        }

        return $user;
    }

    private function makeApplication(User $client): LoanApplication
    {
        foreach (['data_processing', 'credit_bureau_check'] as $type) {
            PopiaConsent::create([
                'user_id' => $client->id,
                'consent_type' => $type,
                'granted' => true,
                'consented_at' => now(),
            ]);
        }

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

    public function test_approving_the_same_application_twice_creates_only_one_loan(): void
    {
        $admin = $this->makeUser(asAdmin: true);
        $client = $this->makeUser();
        $application = $this->makeApplication($client);

        $this->actingAs($admin)->post(route('loans.approve', $application->id), ['approval_comments' => 'first'])
            ->assertRedirect();
        $second = $this->actingAs($admin)->post(route('loans.approve', $application->id), ['approval_comments' => 'second']);

        $second->assertRedirect();
        $this->assertTrue(session()->has('error'), 'Second approve attempt must be rejected, not silently re-processed.');
        $this->assertSame(1, Loan::where('loan_application_id', $application->id)->count());
        $this->assertSame(1, LoanDisbursement::whereHas('loan', fn ($q) => $q->where('loan_application_id', $application->id))->count());
    }

    public function test_rejecting_an_already_approved_application_is_blocked(): void
    {
        $admin = $this->makeUser(asAdmin: true);
        $client = $this->makeUser();
        $application = $this->makeApplication($client);

        $this->actingAs($admin)->post(route('loans.approve', $application->id), ['approval_comments' => 'ok']);

        $response = $this->actingAs($admin)->post(route('loans.reject', $application->id), ['rejection_reason' => 'changed my mind']);

        $response->assertRedirect();
        $this->assertTrue(session()->has('error'));
        $this->assertSame('approved', $application->fresh()->status, 'Application must stay approved — reject() must not silently flip an already-approved application.');
    }

    public function test_rejecting_the_same_disbursement_twice_is_blocked(): void
    {
        $admin = $this->makeUser(asAdmin: true);
        $client = $this->makeUser();
        $application = $this->makeApplication($client);

        $loan = Loan::create([
            'loan_application_id' => $application->id,
            'user_id' => $client->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'approved_amount' => 1000,
            'status' => 'approved',
            'remaining_balance' => 0,
        ]);

        $disbursement = LoanDisbursement::create([
            'loan_id' => $loan->id,
            'disbursed_amount' => 1000,
            'status' => 'waiting_for_approval',
            'approver_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('disbursements.reject', $disbursement->id), ['rejection_reason' => 'first'])
            ->assertRedirect();
        $second = $this->actingAs($admin)->post(route('disbursements.reject', $disbursement->id), ['rejection_reason' => 'second']);

        $second->assertRedirect();
        $this->assertTrue(session()->has('error'));
        $this->assertSame('rejected', $disbursement->fresh()->status);
    }
}
