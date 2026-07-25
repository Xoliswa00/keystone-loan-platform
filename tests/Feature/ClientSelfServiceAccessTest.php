<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * routes/web.php registered `/my-statement` (client.my-statement) twice —
 * once unrestricted in the client auth group, once again inside the
 * staff-only role:admin,loan_officer,finance,it_admin group. Laravel's
 * RouteCollection keys routes by method+URI, so the second registration
 * silently replaced the first: every client hit the staff role check
 * viewing their OWN statement and got 403'd. Same class of bug moved
 * admin.agreements.settlement-quote (a client's NCA s.125 right, triggered
 * from their own loan page) entirely inside that staff-only group, so no
 * client could ever reach it. Both surfaced via the access_denied audit log
 * added this session — see App\Exceptions\Handler.
 */
class ClientSelfServiceAccessTest extends TestCase
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

    private function makeClient(): User
    {
        return User::create([
            'name' => 'Test Client',
            'email' => 'self-service-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
    }

    public function test_client_can_reach_their_own_statement(): void
    {
        $client = $this->makeClient();

        $response = $this->actingAs($client)->get(route('client.my-statement'));

        $this->assertNotSame(403, $response->getStatusCode(), 'client.my-statement must not 403 for the owning client.');
    }

    public function test_client_can_request_a_settlement_quote_on_their_own_loan(): void
    {
        $client = $this->makeClient();

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

        $loan = Loan::create([
            'loan_application_id' => $application->id,
            'user_id' => $client->id,
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

        $response = $this->actingAs($client)->post(route('admin.agreements.settlement-quote', $loan));

        $response->assertRedirect();
        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertDatabaseHas('nca_agreements', [
            'loan_application_id' => $application->id,
            'document_type' => 'settlement_quote',
        ]);
    }

    public function test_client_cannot_request_a_settlement_quote_on_someone_elses_loan(): void
    {
        $owner = $this->makeClient();
        $other = $this->makeClient();

        $application = LoanApplication::create([
            'user_id' => $owner->id,
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
            'user_id' => $owner->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'loan_term_months' => 1,
            'approved_amount' => 1000,
            'status' => 'disbursed',
            'remaining_balance' => 1050,
        ]);

        $this->actingAs($other)
            ->post(route('admin.agreements.settlement-quote', $loan))
            ->assertForbidden();
    }
}
