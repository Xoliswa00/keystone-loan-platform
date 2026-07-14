<?php

namespace App\Console\Commands;

use App\Models\LendingSetting;
use App\Models\RepaymentSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    protected $signature = 'keystone:send-payment-reminders {--days= : Days before due date to send reminder (defaults to admin/settings/lending)}';

    protected $description = 'Send payment reminders to clients with instalments due in N days.';

    public function handle(): int
    {
        // No explicit --days on the command line (the cron-scheduled call)
        // follows the admin-configurable setting; manual runs can still
        // override with e.g. `--days=7`.
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : LendingSetting::current()->payment_reminder_days_before_due;
        $target = now()->addDays($days)->toDateString();

        $schedules = RepaymentSchedule::with(['loanApplication.user', 'loanApplication.loan'])
            ->where('status', 'pending')
            ->whereDate('due_date', $target)
            ->get();

        $this->info("Sending reminders for due date: {$target} ({$schedules->count()} instalments)");

        $sent = 0;

        foreach ($schedules as $schedule) {
            $application = $schedule->loanApplication;
            $user = $application?->user;

            if (! $user || ! $user->email) {
                continue;
            }

            try {
                Mail::to($user->email)->queue(new \App\Mail\LoanNotificationMail(
                    'Payment Reminder — Keystone Capital Partners',
                    [
                        "Dear {$user->name},",
                        'This is a reminder that your instalment of R'.number_format($schedule->emi_amount, 2).' is due on '.Carbon::parse($schedule->due_date)->format('d F Y').'.',
                        'Please ensure sufficient funds are available in your account for the debit order.',
                        'Reference: #'.str_pad($application->id, 6, '0', STR_PAD_LEFT),
                        'If you have any questions, please contact us on WhatsApp: 27721853349',
                    ],
                    $application
                ));
                $sent++;
            } catch (\Exception $e) {
                Log::warning("Reminder failed for user #{$user->id}: ".$e->getMessage());
            }
        }

        $this->info("Sent: {$sent}");

        return self::SUCCESS;
    }
}
