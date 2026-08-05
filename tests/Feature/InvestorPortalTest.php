<?php

namespace Tests\Feature;

use App\Mail\InvestorOtpMail;
use App\Models\FundingFacility;
use App\Models\FundingFacilityTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvestorPortalTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStaff(): User
    {
        return User::create([
            'name' => 'Test Staff',
            'email' => 'investor-portal-staff-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
    }

    private function makeFacility(User $staff, string $idNumber, string $email, string $code): FundingFacility
    {
        return FundingFacility::create([
            'facility_code' => $code,
            'facility_name' => 'Test Facility '.$code,
            'funder_type' => 'private_investor',
            'funder_name' => 'Test Investor',
            'funder_id_or_reg' => $idNumber,
            'contact_email' => $email,
            'facility_limit' => 100000,
            'drawn_amount' => 50000,
            'current_balance' => 50000,
            'interest_rate' => 0.12,
            'status' => 'active',
            'created_by' => $staff->id,
        ]);
    }

    private function extractOtpCode(): string
    {
        $code = '';
        // InvestorOtpMail implements ShouldQueue, so Mail::fake() records it
        // as queued rather than sent — true regardless of the real
        // QUEUE_CONNECTION driver, which is what Laravel's own fake tracks.
        Mail::assertQueued(InvestorOtpMail::class, function (InvestorOtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_valid_id_and_email_sends_otp_and_verifying_grants_dashboard_access(): void
    {
        Mail::fake();
        $staff = $this->makeStaff();
        $facility = $this->makeFacility($staff, '8001015009087', 'investor@example.com', 'FF-TEST-1');

        $this->post(route('investor.login.send'), [
            'id_number' => '8001015009087',
            'email' => 'investor@example.com',
        ])->assertRedirect(route('investor.verify'));

        Mail::assertQueued(InvestorOtpMail::class, 1);
        $code = $this->extractOtpCode();

        $this->post(route('investor.verify.submit'), ['code' => $code])
            ->assertRedirect(route('investor.dashboard'));

        $this->assertTrue(session('investor_authenticated'));

        $response = $this->get(route('investor.dashboard'));
        $response->assertOk();
        $response->assertSee($facility->facility_code);
    }

    public function test_unknown_id_and_email_does_not_send_an_email_but_gives_the_same_response(): void
    {
        Mail::fake();
        $this->makeStaff(); // no facility created

        $this->post(route('investor.login.send'), [
            'id_number' => 'nonexistent-id',
            'email' => 'nobody@example.com',
        ])->assertRedirect(route('investor.verify'));

        Mail::assertNotQueued(InvestorOtpMail::class);
    }

    public function test_wrong_otp_code_is_rejected(): void
    {
        Mail::fake();
        $staff = $this->makeStaff();
        $this->makeFacility($staff, '8001015009087', 'investor@example.com', 'FF-TEST-2');

        $this->post(route('investor.login.send'), [
            'id_number' => '8001015009087',
            'email' => 'investor@example.com',
        ]);

        $this->post(route('investor.verify.submit'), ['code' => '000000'])
            ->assertRedirect();

        $this->assertFalse((bool) session('investor_authenticated'));
    }

    public function test_dashboard_redirects_to_login_when_not_authenticated(): void
    {
        $this->get(route('investor.dashboard'))->assertRedirect(route('investor.login'));
    }

    public function test_investor_only_sees_their_own_facility_not_others(): void
    {
        Mail::fake();
        $staff = $this->makeStaff();
        $ownFacility = $this->makeFacility($staff, '8001015009087', 'investor@example.com', 'FF-OWN');
        $otherFacility = $this->makeFacility($staff, '9002026009088', 'other-investor@example.com', 'FF-OTHER');

        FundingFacilityTransaction::create([
            'facility_id' => $ownFacility->id,
            'transaction_type' => 'drawdown',
            'amount' => 10000,
            'transaction_date' => now()->toDateString(),
            'created_by' => $staff->id,
        ]);

        $this->post(route('investor.login.send'), [
            'id_number' => '8001015009087',
            'email' => 'investor@example.com',
        ]);
        $code = $this->extractOtpCode();
        $this->post(route('investor.verify.submit'), ['code' => $code]);

        $response = $this->get(route('investor.dashboard'));
        $response->assertSee($ownFacility->facility_code);
        $response->assertDontSee($otherFacility->facility_code);
    }

    public function test_logout_clears_investor_session(): void
    {
        Mail::fake();
        $staff = $this->makeStaff();
        $this->makeFacility($staff, '8001015009087', 'investor@example.com', 'FF-TEST-3');

        $this->post(route('investor.login.send'), [
            'id_number' => '8001015009087',
            'email' => 'investor@example.com',
        ]);
        $code = $this->extractOtpCode();
        $this->post(route('investor.verify.submit'), ['code' => $code]);

        $this->post(route('investor.logout'))->assertRedirect(route('investor.login'));

        $this->get(route('investor.dashboard'))->assertRedirect(route('investor.login'));
    }
}
