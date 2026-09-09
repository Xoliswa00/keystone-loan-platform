<?php

namespace Tests\Feature;

use App\Models\AccountSnapshot;
use App\Models\ExportProfile;
use App\Models\ExportRecipient;
use App\Models\ExportRun;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesReportingFixtures;
use Tests\TestCase;

class RunDataExportsCommandTest extends TestCase
{
    use DatabaseTransactions, MakesReportingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['exports.enabled' => true]);
    }

    private function makeProfile(int $dayOfMonth = 5): ExportProfile
    {
        $recipient = ExportRecipient::create([
            'name' => 'Bureau', 'slug' => 'bureau-'.uniqid(), 'kind' => 'bureau',
            'transport' => 'download', 'active' => true,
        ]);

        return ExportProfile::create([
            'export_recipient_id' => $recipient->id,
            'name' => 'Monthly', 'slug' => 'monthly-'.uniqid(),
            'dataset' => 'account_snapshot', 'format' => 'csv',
            'delimiter' => ',', 'enclosure' => '"', 'include_header' => true, 'line_ending' => 'lf',
            'columns' => [['source' => 'account_no', 'label' => 'A', 'format' => 'string']],
            'filename_pattern' => '{profile_slug}_{period}.csv',
            'schedule_day_of_month' => $dayOfMonth, 'period_offset' => 1, 'active' => true,
        ]);
    }

    private function snapshotFor(string $period): void
    {
        $loan = $this->bareLoan();
        AccountSnapshot::create([
            'period' => $period, 'generation' => 1, 'loan_id' => $loan->id,
            'account_no' => 'KL-'.$loan->id, 'arrears_amount' => 0, 'days_past_due' => 0,
            'account_status' => 'Open', 'under_dispute' => false, 'snapshot_at' => now(),
        ]);
    }

    public function test_no_ops_when_feature_disabled(): void
    {
        config(['exports.enabled' => false]);
        $this->makeProfile();

        $this->artisan('keystone:run-data-exports')->assertSuccessful();
        $this->assertSame(0, ExportRun::count());
    }

    public function test_runs_for_forced_period_and_skips_when_already_delivered(): void
    {
        $p = $this->makeProfile();
        $this->snapshotFor('2026-08');

        $this->artisan('keystone:run-data-exports', ['--period' => '2026-08', '--force' => true])->assertSuccessful();
        $this->assertSame(1, ExportRun::where('export_profile_id', $p->id)->where('status', 'success')->count());

        // Second call without --force: already delivered => skip, no new run.
        $this->artisan('keystone:run-data-exports', ['--period' => '2026-08'])->assertSuccessful();
        $this->assertSame(1, ExportRun::where('export_profile_id', $p->id)->count());
    }

    public function test_waits_until_scheduled_day_then_catches_up(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 06:00:00'));
        $period = '2026-08';
        $p = $this->makeProfile(dayOfMonth: 10);
        $this->snapshotFor($period);

        // Day 3 < day 10 => waits, no run.
        $this->artisan('keystone:run-data-exports')->assertSuccessful();
        $this->assertSame(0, ExportRun::where('export_profile_id', $p->id)->count());

        // Day 12: past the scheduled day, still no success => catches up.
        Carbon::setTestNow(Carbon::parse('2026-09-12 06:00:00'));
        $this->artisan('keystone:run-data-exports')->assertSuccessful();
        $this->assertSame('success', ExportRun::where('export_profile_id', $p->id)->value('status'));

        Carbon::setTestNow();
    }

    public function test_rejects_bad_period_option(): void
    {
        $this->makeProfile();
        $this->artisan('keystone:run-data-exports', ['--period' => 'bad'])->assertFailed();
    }
}
