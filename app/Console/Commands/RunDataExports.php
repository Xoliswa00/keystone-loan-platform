<?php

namespace App\Console\Commands;

use App\Models\ExportProfile;
use App\Models\ExportRun;
use App\Services\Export\ExportProfileRunner;
use Illuminate\Console\Command;

class RunDataExports extends Command
{
    protected $signature = 'keystone:run-data-exports
        {--profile= : Only this profile slug}
        {--period= : Force this reporting period YYYY-MM (otherwise each profile resolves its own)}
        {--force : Run even if a successful run already exists / the scheduled day has not arrived}';

    protected $description = 'Build and deliver outbound data-export files for every due export profile.';

    public function handle(ExportProfileRunner $runner): int
    {
        if (! config('exports.enabled')) {
            $this->warn('Outbound data exports are disabled (DATA_EXPORTS_ENABLED=false). Nothing run.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $periodOpt = $this->option('period');

        if ($periodOpt !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodOpt)) {
            $this->error("Invalid --period '{$periodOpt}'. Use YYYY-MM.");

            return self::FAILURE;
        }

        $profiles = ExportProfile::query()
            ->where('active', true)
            ->when($this->option('profile'), fn ($q, $slug) => $q->where('slug', $slug))
            ->with('recipient')
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('No active export profiles match.');

            return self::SUCCESS;
        }

        $today = now();
        $ran = 0;

        foreach ($profiles as $profile) {
            $period = $periodOpt ?: $profile->periodFor($today);

            $alreadyDone = ExportRun::where('export_profile_id', $profile->id)
                ->where('period', $period)
                ->where('status', 'success')
                ->exists();

            if ($alreadyDone && ! $force) {
                $this->line("  skip  {$profile->slug} {$period} — already delivered");

                continue;
            }

            // Catch-up safe: run on or after the configured day; a missed day is
            // simply picked up on a later day because $alreadyDone is still false.
            if (! $force && ! $periodOpt && $today->day < (int) $profile->schedule_day_of_month) {
                $this->line("  wait  {$profile->slug} {$period} — due day {$profile->schedule_day_of_month}");

                continue;
            }

            $run = $runner->run($profile, $period, 'schedule');
            $ran++;

            $line = "  {$run->status}  {$profile->slug} {$period} — {$run->row_count} rows";
            $run->status === 'failed' || $run->status === 'skipped'
                ? $this->warn($line.($run->error_message ? " ({$run->error_message})" : ''))
                : $this->info($line);
        }

        $this->info("Done. {$ran} run(s) executed.");

        return self::SUCCESS;
    }
}
