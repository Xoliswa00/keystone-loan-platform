<?php

namespace Tests\Feature;

use App\Models\AccountSnapshot;
use App\Models\ExportProfile;
use App\Models\ExportRecipient;
use App\Services\Export\ExportProfileRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesReportingFixtures;
use Tests\TestCase;

class ExportProfileRunnerTest extends TestCase
{
    use DatabaseTransactions, MakesReportingFixtures;

    private string $period = '2026-08';

    protected function setUp(): void
    {
        parent::setUp();
        config(['exports.enabled' => true, 'exports.disk' => 'local']);
    }

    private function snapshot(array $overrides = []): AccountSnapshot
    {
        $loan = $this->bareLoan();

        return AccountSnapshot::create(array_merge([
            'period' => $this->period,
            'generation' => 1,
            'loan_id' => $loan->id,
            'account_no' => 'KL-'.$loan->id,
            'borrower_name' => 'Test Person',
            'arrears_amount' => 0,
            'days_past_due' => 0,
            'account_status' => 'Open',
            'under_dispute' => false,
            'snapshot_at' => now(),
        ], $overrides));
    }

    private function recipient(array $overrides = []): ExportRecipient
    {
        return ExportRecipient::create(array_merge([
            'name' => 'Test Bureau',
            'slug' => 'test-bureau-'.uniqid(),
            'kind' => 'bureau',
            'transport' => 'download',
            'active' => true,
        ], $overrides));
    }

    private function profile(ExportRecipient $recipient, array $overrides = []): ExportProfile
    {
        return ExportProfile::create(array_merge([
            'export_recipient_id' => $recipient->id,
            'name' => 'Monthly',
            'slug' => 'monthly-'.uniqid(),
            'dataset' => 'account_snapshot',
            'format' => 'csv',
            'delimiter' => ',',
            'enclosure' => '"',
            'include_header' => true,
            'line_ending' => 'lf',
            'columns' => [
                ['source' => 'account_no', 'label' => 'Account', 'format' => 'string'],
                ['source' => 'arrears_amount', 'label' => 'Arrears', 'format' => 'money'],
            ],
            'filename_pattern' => '{recipient_slug}_{period}.csv',
            'schedule_day_of_month' => 5,
            'period_offset' => 1,
            'active' => true,
        ], $overrides));
    }

    public function test_successful_run_writes_file_and_records_evidence(): void
    {
        $r = $this->recipient();
        $p = $this->profile($r);
        $this->snapshot(['arrears_amount' => 150.25]);
        $this->snapshot(['arrears_amount' => 0]);

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->row_count);
        $this->assertNotNull($run->file_sha256);
        $this->assertTrue(Storage::disk('local')->exists($run->file_path));
        $this->assertNotEmpty($run->profile_snapshot['columns']);
        $this->assertStringContainsString('Account,Arrears', Storage::disk('local')->get($run->file_path));
        $this->assertDatabaseHas('audit_logs', ['event' => 'exported', 'auditable_id' => $p->id]);
    }

    public function test_run_skipped_when_feature_disabled(): void
    {
        config(['exports.enabled' => false]);
        $p = $this->profile($this->recipient());
        $this->snapshot();

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('skipped', $run->status);
        $this->assertNull($run->file_path);
    }

    public function test_run_skipped_when_no_snapshot_for_period(): void
    {
        $p = $this->profile($this->recipient());

        $run = app(ExportProfileRunner::class)->run($p, '2026-01', 'schedule');

        $this->assertSame('skipped', $run->status);
        $this->assertStringContainsString('No account snapshot', $run->error_message);
    }

    public function test_disputed_rows_excluded_from_real_run_but_present_in_preview(): void
    {
        $r = $this->recipient();
        $p = $this->profile($r);
        $this->snapshot(['under_dispute' => false]);
        $this->snapshot(['under_dispute' => true, 'dispute_reference' => 'DSP-9']);

        $real = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');
        $this->assertSame(1, $real->row_count);

        $preview = app(ExportProfileRunner::class)->run($p, $this->period, 'preview', dryRun: true);
        $this->assertSame('built', $preview->status);
        $this->assertSame(2, $preview->row_count);
    }

    public function test_failure_is_isolated_and_context_is_scrubbed(): void
    {
        config(['exports.max_rows_per_run' => 0]);
        $p = $this->profile($this->recipient());
        $this->snapshot();

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('failed', $run->status);
        $this->assertSame('RuntimeException', $run->error_context['exception']);
        $this->assertArrayNotHasKey('rows_sample', $run->error_context);
        $this->assertStringNotContainsString('Test Person', json_encode($run->error_context));
    }

    public function test_filename_pattern_cannot_traverse_paths(): void
    {
        $r = $this->recipient();
        $p = $this->profile($r, ['filename_pattern' => '../../{period}.csv']);
        $this->snapshot();

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('success', $run->status);
        $this->assertStringNotContainsString('..', $run->file_path);
        $this->assertStringContainsString($p->slug.'/', $run->file_path);
    }

    public function test_email_transport_refused_for_bureau_recipient(): void
    {
        config(['exports.email_domain_allowlist' => ['bureau.co.za']]);
        $r = $this->recipient(['transport' => 'email', 'email_recipients' => ['ops@bureau.co.za']]);
        $p = $this->profile($r, ['columns' => [['source' => 'account_no', 'label' => 'A', 'format' => 'string']]]);
        $this->snapshot();

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('bureau', strtolower($run->error_message));
    }

    public function test_email_transport_requires_allowlisted_domain(): void
    {
        Mail::fake();
        config(['exports.email_domain_allowlist' => ['approved.example']]);
        $r = $this->recipient([
            'kind' => 'partner',
            'transport' => 'email',
            'email_recipients' => ['someone@not-approved.example'],
        ]);
        $p = $this->profile($r, ['columns' => [['source' => 'account_no', 'label' => 'A', 'format' => 'string']]]);
        $this->snapshot();

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('failed', $run->status);
        Mail::assertNothingSent();
    }

    public function test_email_transport_sends_for_non_pii_partner_extract(): void
    {
        Mail::fake();
        config(['exports.email_domain_allowlist' => ['approved.example']]);
        $r = $this->recipient([
            'kind' => 'partner',
            'transport' => 'email',
            'email_recipients' => ['ops@approved.example'],
        ]);
        $p = $this->profile($r, ['columns' => [
            ['source' => 'account_no', 'label' => 'A', 'format' => 'string'],
            ['source' => 'arrears_amount', 'label' => 'Arrears', 'format' => 'money'],
        ]]);
        $this->snapshot();

        $run = app(ExportProfileRunner::class)->run($p, $this->period, 'schedule');

        $this->assertSame('success', $run->status);
        Mail::assertSent(\App\Mail\DataExportMail::class);
        $this->assertContains('ops@approved.example', $run->transport_result['to']);
    }
}
