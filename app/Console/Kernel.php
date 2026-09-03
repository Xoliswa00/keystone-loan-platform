<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ── Payment reminders ── days-before-due-date is admin-configurable
        // (admin/settings/lending); no --days flag here so the cron follows
        // the setting instead of a fixed value baked into the schedule.
        $schedule->command('keystone:send-payment-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/reminders.log'));

        // ── Arrears escalation ── daily at 09:00 (DPD tiers admin-configurable via admin/settings/lending)
        $schedule->command('keystone:escalate-arrears')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/escalations.log'));

        // ── IFRS 9 Monthly provisioning ── last day of every month at 22:00
        $schedule->command('keystone:provision-monthly')
            ->monthlyOn(28, '22:00')  // runs on 28th — safe for all months
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/provisioning.log'));

        // ── GL reconciliation ── every weekday morning at 07:00
        $schedule->command('keystone:reconcile-gl')
            ->weekdays()
            ->at('07:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/gl_recon.log'));

        // ── Funding facility interest accrual ── last day of month (same time as provisioning)
        $schedule->command('keystone:accrue-facility-interest')
            ->monthlyOn(28, '22:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/facility_interest.log'));

        // ── Failed jobs cleanup ── weekly on Sunday
        $schedule->command('queue:retry all')
            ->weekly()
            ->sundays()
            ->at('02:00');

        // ── Xquisite monitoring heartbeat ── sync, not queued, so a dead queue
        // worker can't mask an outage (see App\Jobs\ReportHealthStatus).
        $schedule->job(new \App\Jobs\ReportHealthStatus)->everyFiveMinutes();

        // ── Xquisite error forwarding ── ships error+ system_logs rows to the
        // central hub so all apps' errors are visible in one place.
        $schedule->command('keystone:report-errors')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/xquisite-forward.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
