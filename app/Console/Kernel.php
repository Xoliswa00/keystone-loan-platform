<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ── Payment reminders ── 3 days before due date, every morning at 08:00
        $schedule->command('keystone:send-payment-reminders --days=3')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/reminders.log'));

        // ── Arrears escalation ── daily at 09:00 (emails for 30/60/90 DPD buckets)
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
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
