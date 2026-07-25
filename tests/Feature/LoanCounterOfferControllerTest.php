<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\LoanCounterOffer;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staff propose a counter-offer bounded server-side by AffordabilityService
 * (never their own unbounded judgement — reckless-lending / NCA s.81
 * exposure), and the client explicitly accepts or declines it. accept() also
 * re-validates against a fresh affordability calculation immediately before
 * finalizing terms — an offer sitting unactioned long enough for the
 * client's circumstances to change must not be accepted verbatim.
 */
class LoanCounterOfferControllerTest extends TestCase
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
            'email' => 'counter-offer-staff-'.uniqid('', true).'@example.com',
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
     * income 15000 - expenses 5500 = disposable 9500; default
     * affordability_ratio 0.30 → max_instalment 2850 (matches
     * CloDecisionEngineTest's fixture so behaviour stays consistent across
     * suites).
     */
    private function makeClient(float $netIncome = 15000): User
    {
        $user = User::create([
            'name' => 'Test Client',
            'email' => 'counter-offer-client-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        DB::table('customer_profiles')->insert([
            'user_id' => $user->id,
            'employment_tenure' => 'over_3y',
            'net_monthly_income' => $netIncome,
            'expense_housing' => 2000,
            'expense_transport' => 1000,
            'expense_existing_debt' => 0,
            'expense_insurance' => 500,
            'expense_living' => 2000,
            'profile_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('popia_consents')->insert([
            'user_id' => $user->id,
            'consent_type' => 'data_processing',
            'granted' => true,
            'consented_at' => now(),
        ]);

        return $user;
    }

    private function makeApplication(User $client, array $overrides = []): LoanApplication
    {
        return LoanApplication::create(array_merge([
            'user_id' => $client->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 10000, // deliberately over affordability, matching an affordability_review fixture
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'affordability_review',
            'reviewer_id' => null,
            'affordability_checked' => true,
            'affordability_disposable_income' => 9500,
            'affordability_max_instalment' => 2850,
            'affordability_instalment_requested' => 12000,
        ], $overrides));
    }

    public function test_store_rejects_amount_exceeding_affordability_ceiling(): void
    {
        $staff = $this->makeStaff();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        $response = $this->actingAs($staff)->post(
            route('admin.counter-offers.store', $application),
            ['loan_product_id' => $this->product->id, 'amount' => 100000, 'months' => 1]
        );

        $response->assertRedirect();
        $this->assertTrue(session()->has('error'));
        $this->assertDatabaseCount('loan_counter_offers', 0);
    }

    public function test_store_creates_pending_offer_and_supersedes_prior_pending_offer(): void
    {
        $staff = $this->makeStaff();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        $existing = LoanCounterOffer::create([
            'loan_application_id' => $application->id,
            'loan_product_id' => $this->product->id,
            'amount' => 500,
            'months' => 1,
            'instalment' => 600,
            'proposed_by' => $staff->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($staff)->post(
            route('admin.counter-offers.store', $application),
            ['loan_product_id' => $this->product->id, 'amount' => 500, 'months' => 1]
        );

        $response->assertRedirect();
        $this->assertTrue(session()->has('success'));
        $this->assertSame('superseded', $existing->fresh()->status);
        $this->assertDatabaseHas('loan_counter_offers', [
            'loan_application_id' => $application->id,
            'status' => 'pending',
            'amount' => 500,
        ]);
    }

    public function test_accept_finalizes_terms_and_moves_affordability_review_to_pending(): void
    {
        $staff = $this->makeStaff();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        $offer = LoanCounterOffer::create([
            'loan_application_id' => $application->id,
            'loan_product_id' => $this->product->id,
            'amount' => 500,
            'months' => 1,
            'instalment' => 600,
            'proposed_by' => $staff->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($client)->post(route('counter-offers.accept', $application));

        $response->assertRedirect(route('loanapplications.show', $application->id));
        $application->refresh();
        $this->assertSame('pending', $application->status);
        $this->assertEquals(500, (float) $application->loan_amount);
        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertDatabaseHas('loan_fees', ['loan_application_id' => $application->id]);
        $this->assertDatabaseHas('repayment_schedules', ['loan_id' => $application->id]);
    }

    public function test_accept_rejects_offer_that_no_longer_fits_current_affordability(): void
    {
        $staff = $this->makeStaff();
        $client = $this->makeClient(); // starts affordable at max_instalment 2850
        $application = $this->makeApplication($client);

        // Affordable when proposed: R500/1mo instalment is well under 2850.
        $offer = LoanCounterOffer::create([
            'loan_application_id' => $application->id,
            'loan_product_id' => $this->product->id,
            'amount' => 500,
            'months' => 1,
            'instalment' => 600,
            'proposed_by' => $staff->id,
            'status' => 'pending',
        ]);

        // Client's circumstances change materially before they act on it —
        // income drops so far that even R500 is no longer affordable.
        DB::table('customer_profiles')->where('user_id', $client->id)->update(['net_monthly_income' => 100]);

        $response = $this->actingAs($client)->post(route('counter-offers.accept', $application));

        $response->assertRedirect();
        $this->assertTrue(session()->has('error'));
        $application->refresh();
        $this->assertSame('affordability_review', $application->status, 'Application must not be finalized on a now-unaffordable offer.');
        $this->assertSame('pending', $offer->fresh()->status, 'Offer must remain pending, not silently accepted.');
        $this->assertDatabaseMissing('loan_fees', ['loan_application_id' => $application->id]);
    }

    public function test_decline_on_affordability_review_rejects_the_application(): void
    {
        $staff = $this->makeStaff();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        LoanCounterOffer::create([
            'loan_application_id' => $application->id,
            'loan_product_id' => $this->product->id,
            'amount' => 500,
            'months' => 1,
            'instalment' => 600,
            'proposed_by' => $staff->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($client)->post(route('counter-offers.decline', $application));

        $response->assertRedirect(route('loanapplications.show', $application->id));
        $this->assertSame('rejected', $application->fresh()->status);
    }

    public function test_decline_on_pending_application_leaves_original_terms_unchanged(): void
    {
        $staff = $this->makeStaff();
        $client = $this->makeClient();
        $application = $this->makeApplication($client, [
            'status' => 'pending',
            'loan_amount' => 500, // already affordable — this is the "offer on a normal application" case
        ]);

        LoanCounterOffer::create([
            'loan_application_id' => $application->id,
            'loan_product_id' => $this->product->id,
            'amount' => 400,
            'months' => 1,
            'instalment' => 480,
            'proposed_by' => $staff->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($client)->post(route('counter-offers.decline', $application));

        $response->assertRedirect(route('loanapplications.show', $application->id));
        $application->refresh();
        $this->assertSame('pending', $application->status);
        $this->assertEquals(500, (float) $application->loan_amount);
    }
}
