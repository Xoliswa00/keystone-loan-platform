<?php

namespace Tests\Feature\Security;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\NcrExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One test per injection hole found in the 2026-10-09 security audit. Each
 * replays the attack that used to work.
 */
class InjectionRegressionTest extends TestCase
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

    // ── Stored XSS: a replaced bank statement opened by staff ───────────────

    public function test_a_client_cannot_replace_a_bank_statement_with_an_html_file(): void
    {
        Storage::fake('local');
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);

        $this->actingAs($client)->put(route('applications.update', $application->id), [
            'loan_type' => 'personal', 'loan_amount' => 1000, 'purpose' => 'Personal',
            'bank_statement' => UploadedFile::fake()->createWithContent('statement.html', '<script>alert(document.cookie)</script>'),
        ])->assertSessionHasErrors('bank_statement');

        $this->assertNull($application->fresh()->bank_statement);
    }

    public function test_a_client_cannot_replace_payslips_with_an_html_file(): void
    {
        Storage::fake('local');
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);

        $this->actingAs($client)->put(route('applications.update', $application->id), [
            'loan_type' => 'personal', 'loan_amount' => 1000, 'purpose' => 'Personal',
            'payslips' => UploadedFile::fake()->createWithContent('payslip.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertSessionHasErrors('payslips');
    }

    public function test_a_pdf_bank_statement_can_still_be_replaced(): void
    {
        Storage::fake('local');
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);

        $this->actingAs($client)->put(route('applications.update', $application->id), [
            'loan_type' => 'personal', 'loan_amount' => 1000, 'purpose' => 'Personal',
            'bank_statement' => UploadedFile::fake()->create('statement.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($application->fresh()->bank_statement);
    }

    public function test_a_stored_html_document_downloads_instead_of_opening_as_a_page(): void
    {
        // A file saved before the upload rule existed must still be harmless.
        Storage::fake('local');
        Storage::disk('local')->put('bank_statements/old.html', '<script>alert(document.cookie)</script>');
        $client = $this->makeUser();
        $application = $this->pendingApplication($client);
        $application->forceFill(['bank_statement' => 'bank_statements/old.html'])->save();

        $response = $this->actingAs($this->makeUser('admin'))
            ->get(route('secure-documents.application-file', ['application' => $application->id, 'field' => 'bank_statement']))
            ->assertOk();

        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    // ── CSV formula injection: NCR return ───────────────────────────────────

    public function test_a_client_name_cannot_inject_a_formula_into_the_ncr_return(): void
    {
        $loan = (object) [
            'application_id' => 1, 'loan_id' => 1, 'customer_code' => 'C001', 'id_number' => '9001015009087',
            'full_name' => '=HYPERLINK("https://evil.example","Click")', 'age' => 35, 'gender' => '@SUM(A1:A9)',
            'loan_type' => 'personal', 'nca_credit_type' => 'short_term', 'ncr_purpose_code' => 'OTHER',
            'principal_amount' => 1000, 'initiation_fee' => 150, 'service_fee' => 60, 'interest_amount' => 50,
            'total_credit_cost' => 260, 'term_months' => 1, 'disbursed_date' => '2026-01-15',
            'loan_status' => 'active', 'remaining_balance' => 1260, 'account_status' => 'current',
            'dti_ratio' => 0.2, 'credit_bureau_score' => '650', 'credit_bureau_provider' => '+cmd|calc',
        ];

        $csv = app(NcrExportService::class)->toCsv(collect([$loan]));

        $row = str_getcsv(explode("\n", trim($csv))[1], ',', '"', '');

        foreach ($row as $cell) {
            $this->assertDoesNotMatchRegularExpression('/^[=+@]/', $cell, "cell would run as a formula: {$cell}");
        }
        $this->assertContains("'=HYPERLINK(\"https://evil.example\",\"Click\")", $row);
        $this->assertContains('9001015009087', $row, 'an ID number stays a plain value');
    }

    public function test_the_ncr_quarter_must_be_exactly_a_year_and_quarter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Used to pass the unanchored check and reach the Content-Disposition header.
        app(NcrExportService::class)->loansForQuarter('2026-Q1"; filename="evil.html');
    }

    // ── Email header / recipient injection: notification CC ─────────────────

    private function companySettings(array $overrides): array
    {
        return array_merge([
            'name' => 'Keystone', 'registration_no' => '2020/000000/07', 'ncr_number' => 'NCRCP00000',
            'ncr_credit_category' => 'short_term', 'physical_address' => '1 Main Road', 'phone' => '0110000000',
            'email' => 'info@example.com', 'authorised_signatory' => 'A Signatory',
        ], $overrides);
    }

    public function test_the_notification_cc_must_be_a_list_of_real_email_addresses(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put(route('admin.settings.company.update'), $this->companySettings([
            'notification_cc' => "ops@example.com, attacker@evil.example\r\nBcc: everyone@evil.example",
        ]))->assertSessionHasErrors('notification_cc');

        $this->actingAs($admin)->put(route('admin.settings.company.update'), $this->companySettings([
            'notification_cc' => 'not-an-address',
        ]))->assertSessionHasErrors('notification_cc');
    }

    public function test_a_valid_notification_cc_list_is_accepted(): void
    {
        $this->actingAs($this->makeUser('admin'))->put(route('admin.settings.company.update'), $this->companySettings([
            'notification_cc' => 'ops@example.com, finance@example.com',
        ]))->assertSessionHasNoErrors();
    }

    public function test_an_svg_logo_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->makeUser('admin'))->put(route('admin.settings.company.update'), $this->companySettings([
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]))->assertSessionHasErrors('logo');
    }

    // ── Regex injection: system log viewer ──────────────────────────────────

    public function test_the_log_viewer_ignores_a_regex_in_the_level_or_date(): void
    {
        // Point storage at a scratch folder holding one log line, so the
        // viewer has something to run its pattern against.
        $storage = sys_get_temp_dir().'/keystone-log-test-'.uniqid();
        mkdir($storage.'/logs', 0777, true);
        file_put_contents($storage.'/logs/laravel.log', '['.now()->toDateString().' 10:00:00] testing.ERROR: boom
');
        $this->app->useStoragePath($storage);

        try {
            // An unclosed bracket used to break the pattern and return a 500.
            $this->actingAs($this->makeUser('admin'))
                ->get(route('admin.system.logs', ['level' => '(', 'date' => '(']))
                ->assertOk();
        } finally {
            unlink($storage.'/logs/laravel.log');
            rmdir($storage.'/logs');
            rmdir($storage);
        }
    }
}
