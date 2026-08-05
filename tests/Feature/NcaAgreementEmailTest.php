<?php

namespace Tests\Feature;

use App\Mail\NcaAgreementMail;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\NcaAgreement;
use App\Models\User;
use App\Services\LoanAgreementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NcaAgreementEmailTest extends TestCase
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
            'email' => 'agreement-mail-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
    }

    private function makeApplication(User $client): LoanApplication
    {
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

    public function test_generating_pre_agreement_emails_the_client_with_the_pdf_attached(): void
    {
        Mail::fake();

        $staff = $this->makeClient();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        $agreement = app(LoanAgreementService::class)->generatePreAgreement($application, $staff->id);

        Mail::assertSent(NcaAgreementMail::class, function (NcaAgreementMail $mail) use ($agreement, $client) {
            return $mail->agreement->is($agreement)
                && $mail->hasTo($client->email)
                && count($mail->attachments()) === 1;
        });

        $this->assertNotNull($agreement->fresh()->sent_at);
    }

    public function test_generating_loan_agreement_emails_the_client(): void
    {
        Mail::fake();

        $staff = $this->makeClient();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        $agreement = app(LoanAgreementService::class)->generateLoanAgreement($application, $staff->id);

        Mail::assertSent(NcaAgreementMail::class, fn (NcaAgreementMail $mail) => $mail->hasTo($client->email));
        $this->assertNotNull($agreement->fresh()->sent_at);
    }

    public function test_regenerating_an_existing_agreement_does_not_resend_the_email(): void
    {
        Mail::fake();

        $staff = $this->makeClient();
        $client = $this->makeClient();
        $application = $this->makeApplication($client);

        $service = app(LoanAgreementService::class);
        $first = $service->generatePreAgreement($application, $staff->id);
        $service->generatePreAgreement($application, $staff->id);

        Mail::assertSent(NcaAgreementMail::class, 1);
        $this->assertSame(1, NcaAgreement::where('loan_application_id', $application->id)->count());
    }
}
