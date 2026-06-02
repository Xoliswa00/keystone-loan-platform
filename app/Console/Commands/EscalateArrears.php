<?php

namespace App\Console\Commands;

use App\Models\RepaymentSchedule;
use App\Models\Loan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EscalateArrears extends Command
{
    protected $signature   = 'keystone:escalate-arrears';
    protected $description = 'Send escalation notices for overdue accounts by DPD bucket (30/60/90 days).';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Group overdue schedules by DPD bucket
        $overdue = RepaymentSchedule::with(['loanApplication.user', 'loanApplication.loan'])
            ->whereIn('status', ['pending', 'payment_failed'])
            ->whereDate('due_date', '<', $today)
            ->get()
            ->groupBy(function ($s) {
                $dpd = now()->diffInDays($s->due_date, false) * -1;
                return match(true) {
                    $dpd >= 90 => '90+',
                    $dpd >= 60 => '60-89',
                    $dpd >= 30 => '30-59',
                    default    => '1-29',
                };
            });

        $total = 0;

        foreach ($overdue as $bucket => $schedules) {
            foreach ($schedules as $schedule) {
                $application = $schedule->loanApplication;
                $user        = $application?->user;

                if (!$user || !$user->email) {
                    continue;
                }

                $dpd = (int) (now()->diffInDays($schedule->due_date, false) * -1);

                $subject = match($bucket) {
                    '90+'   => 'URGENT: Account in Default — Keystone Capital Partners',
                    '60-89' => 'Second Notice: Overdue Payment — Keystone Capital Partners',
                    '30-59' => 'Payment Overdue Notice — Keystone Capital Partners',
                    default => 'Missed Payment Reminder — Keystone Capital Partners',
                };

                $lines = match($bucket) {
                    '90+' => [
                        "Dear {$user->name},",
                        "Your account is now {$dpd} days overdue and has been classified as non-performing.",
                        "Outstanding amount: R" . number_format($schedule->emi_amount, 2),
                        "Failure to pay may result in legal action and adverse credit bureau listing.",
                        "Contact us immediately to arrange payment: 27721853349",
                    ],
                    '60-89' => [
                        "Dear {$user->name},",
                        "Your payment is {$dpd} days overdue. This is your second notice.",
                        "Outstanding: R" . number_format($schedule->emi_amount, 2),
                        "Please make payment immediately or contact us to discuss arrangements.",
                    ],
                    default => [
                        "Dear {$user->name},",
                        "Your payment of R" . number_format($schedule->emi_amount, 2) . " is {$dpd} day(s) overdue.",
                        "Please make payment as soon as possible to avoid additional charges.",
                        "Contact us: 27721853349",
                    ],
                };

                try {
                    Mail::to($user->email)->queue(
                        new \App\Mail\LoanNotificationMail($subject, $lines, $application)
                    );
                    $total++;
                } catch (\Exception $e) {
                    Log::warning("Escalation email failed for user #{$user->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Escalation notices sent: {$total}");
        foreach ($overdue as $bucket => $items) {
            $this->line("  [{$bucket} DPD]: {$items->count()} accounts");
        }

        return self::SUCCESS;
    }
}
