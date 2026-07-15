<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\PopiaConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A client can withdraw POPIA/credit-bureau consent from their profile, and
 * staff can override CloDecisionEngine's advisory REVIEW recommendation —
 * these tests guard the two hard stops that must exist regardless: loans
 * cannot be approved without current consent, and consent cannot be
 * withdrawn while a loan is active (see LoanController::approveApplication()
 * and PopiaConsentController::update()).
 */
class ConsentGatingTest extends TestCase
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
            'email' => 'consent-test-'.uniqid('', true).'@example.com',
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

    private function makeApplication(User $user): LoanApplication
    {
        return LoanApplication::create([
            'user_id' => $user->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'pending',
        ]);
    }

    public function test_approval_is_blocked_without_consent(): void
    {
        $admin = $this->makeUser(asAdmin: true);
        $client = $this->makeUser();
        $application = $this->makeApplication($client);
        // No popia_consents rows for this client at all.

        $response = $this->actingAs($admin)
            ->post(route('loans.approve', $application->id), ['approval_comments' => 'test']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session()->has('error'));
        $this->assertDatabaseMissing('loans', ['loan_application_id' => $application->id]);
    }

    public function test_approval_succeeds_with_both_consents_granted(): void
    {
        $admin = $this->makeUser(asAdmin: true);
        $client = $this->makeUser();
        $application = $this->makeApplication($client);

        foreach (['data_processing', 'credit_bureau_check'] as $type) {
            PopiaConsent::create([
                'user_id' => $client->id,
                'consent_type' => $type,
                'granted' => true,
                'consented_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->post(route('loans.approve', $application->id), ['approval_comments' => 'test']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_withdrawing_consent_is_blocked_with_an_active_loan(): void
    {
        $client = $this->makeUser();
        PopiaConsent::create([
            'user_id' => $client->id,
            'consent_type' => 'data_processing',
            'granted' => true,
            'consented_at' => now(),
        ]);

        $application = $this->makeApplication($client);
        Loan::create([
            'loan_application_id' => $application->id,
            'user_id' => $client->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'approved_amount' => 1000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($client)
            ->post(route('profile.consent.update'), ['consent_type' => 'data_processing', 'granted' => '0']);

        $response->assertRedirect(route('profile.edit'));
        $this->assertTrue(session()->has('error'));
        $this->assertTrue(PopiaConsent::isGranted($client->id, 'data_processing'));
    }

    public function test_withdrawing_consent_succeeds_without_an_active_loan(): void
    {
        $client = $this->makeUser();
        PopiaConsent::create([
            'user_id' => $client->id,
            'consent_type' => 'data_processing',
            'granted' => true,
            'consented_at' => now(),
        ]);

        $response = $this->actingAs($client)
            ->post(route('profile.consent.update'), ['consent_type' => 'data_processing', 'granted' => '0']);

        $response->assertRedirect(route('profile.edit'));
        $this->assertFalse(PopiaConsent::isGranted($client->id, 'data_processing'));
    }
}
