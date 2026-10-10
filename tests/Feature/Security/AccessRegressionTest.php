<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The two non-injection holes from the 2026-10-09 security audit: a client
 * rewriting an assessed loan amount, and any staff member redirecting where
 * loan emails are copied.
 */
class AccessRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(?string $role = null): User
    {
        $user = User::create([
            'name' => 'Security Test',
            'email' => 'security-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        if ($role) {
            $user->forceFill(['system_role' => $role])->save();
        }

        return $user;
    }

    private function pendingApplication(User $client): LoanApplication
    {
        $product = LoanProduct::firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Loan', 'min_amount' => 500.00, 'max_amount' => 3000.00,
                'min_months' => 1, 'max_months' => 1, 'monthly_interest_rate' => 0.0500,
                'initiation_fee_flat' => 150.00, 'initiation_fee_rate' => 0.10, 'initiation_fee_cap' => 1050.00,
                'monthly_service_fee' => 60.00, 'vat_rate' => 0.15,
                'requires_enhanced_affordability' => false, 'active' => true,
            ]
        );

        return LoanApplication::create([
            'user_id' => $client->id, 'loan_product_id' => $product->id, 'loan_type' => 'personal',
            'loan_term_months' => 1, 'loan_amount' => 1000, 'purpose' => 'Personal',
            'terms_conditions' => true, 'status' => 'pending',
        ]);
    }

    // ── Loan amount is fixed once assessed ──────────────────────────────────

    public function test_a_client_cannot_raise_the_loan_amount_after_applying(): void
    {
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);

        $this->actingAs($client)->put(route('applications.update', $application->id), [
            'loan_type' => 'personal', 'loan_amount' => 250000, 'purpose' => 'Personal',
        ])->assertSessionHasErrors('loan_amount');

        $this->assertEquals(1000, $application->fresh()->loan_amount);
    }

    public function test_a_client_cannot_lower_the_loan_amount_either(): void
    {
        // Lowering it would still leave the fee record and schedule out of step.
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);

        $this->actingAs($client)->put(route('applications.update', $application->id), [
            'loan_type' => 'personal', 'loan_amount' => 600, 'purpose' => 'Personal',
        ])->assertSessionHasErrors('loan_amount');

        $this->assertEquals(1000, $application->fresh()->loan_amount);
    }

    public function test_a_client_can_still_edit_the_purpose_with_the_amount_unchanged(): void
    {
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);

        $this->actingAs($client)->put(route('applications.update', $application->id), [
            'loan_type' => 'personal', 'loan_amount' => '1000.00', 'purpose' => 'School fees',
        ])->assertSessionHasNoErrors();

        $this->assertSame('School fees', $application->fresh()->purpose);
        $this->assertEquals(1000, $application->fresh()->loan_amount);
    }

    // ── Email routing is admin only ─────────────────────────────────────────

    private function companySettings(array $overrides): array
    {
        return array_merge([
            'name' => 'Keystone', 'registration_no' => '2020/000000/07', 'ncr_number' => 'NCRCP00000',
            'ncr_credit_category' => 'short_term', 'physical_address' => '1 Main Road', 'phone' => '0110000000',
            'email' => 'info@example.com', 'authorised_signatory' => 'A Signatory',
        ], $overrides);
    }

    public function test_a_loan_officer_cannot_change_who_is_copied_on_loan_emails(): void
    {
        $this->actingAs($this->makeUser('admin'))->put(route('admin.settings.company.update'), $this->companySettings([
            'notification_cc' => 'compliance@example.com',
        ]));

        $response = $this->actingAs($this->makeUser('loan_officer'))->put(route('admin.settings.company.update'), $this->companySettings([
            'tagline' => 'Edited by a loan officer',
            'notification_cc' => 'someone-else@example.net',
            'notification_from_email' => 'spoof@example.net',
        ]));

        $company = Company::first();

        if ($response->status() !== 403) {
            // Company details may still be edited by staff; the routing fields may not.
            $this->assertSame('Edited by a loan officer', $company->tagline);
        }
        $this->assertSame('compliance@example.com', $company->notification_cc);
        $this->assertNotSame('spoof@example.net', $company->notification_from_email);
    }

    public function test_an_it_admin_can_change_the_copied_addresses(): void
    {
        $this->actingAs($this->makeUser('it_admin'))->put(route('admin.settings.company.update'), $this->companySettings([
            'notification_cc' => 'ops@example.com',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('ops@example.com', Company::first()->notification_cc);
    }
}
