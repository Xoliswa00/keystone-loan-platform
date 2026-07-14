<?php

namespace App\Console\Commands;

use App\Models\LendingSetting;
use App\Models\RepaymentSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EscalateArrears extends Command
{
    protected $signature = 'keystone:escalate-arrears';

    protected $description = 'Send escalation notices for overdue accounts by DPD tier (tunable via admin/settings/lending).';

    public function handle(): int
    {
        $today = now()->toDateString();
        $settings = LendingSetting::current();
        $stage2Dpd = $settings->ifrs9_stage2_dpd;
        $secondNoticeDpd = $settings->arrears_second_notice_dpd;
        $stage3Dpd = $settings->ifrs9_stage3_dpd;

        // Tiers (not the raw DPD boundaries) drive the message/subject match
        // below, so a threshold change in admin/settings/lending can't
        // silently fall through to the "default" (mildest) notice text.
        $tierFor = fn (int $dpd): int => match (true) {
            $dpd >= $stage3Dpd => 3,
            $dpd >= $secondNoticeDpd => 2,
            $dpd >= $stage2Dpd => 1,
            default => 0,
        };

        $overdue = RepaymentSchedule::with(['loanApplication.user', 'loanApplication.loan'])
            ->whereIn('status', ['pending', 'payment_failed'])
            ->whereDate('due_date', '<', $today)
            ->get()
            ->groupBy(fn ($s) => $tierFor((int) max(0, now()->diffInDays($s->due_date, false) * -1)));

        $total = 0;

        foreach ($overdue as $tier => $schedules) {
            foreach ($schedules as $schedule) {
                $application = $schedule->loanApplication;
                $user = $application?->user;

                if (! $user || ! $user->email) {
                    continue;
                }

                $dpd = (int) max(0, now()->diffInDays($schedule->due_date, false) * -1);

                $subject = match ($tier) {
                    3 => 'URGENT: Account in Default — Keystone Capital Partners',
                    2 => 'Second Notice: Overdue Payment — Keystone Capital Partners',
                    1 => 'Payment Overdue Notice — Keystone Capital Partners',
                    default => 'Missed Payment Reminder — Keystone Capital Partners',
                };

                $lines = match ($tier) {
                    3 => [
                        "Dear {$user->name},",
                        "Your account is now {$dpd} days overdue and has been classified as non-performing.",
                        'Outstanding amount: R'.number_format($schedule->emi_amount, 2),
                        'Failure to pay may result in legal action and adverse credit bureau listing.',
                        'Contact us immediately to arrange payment: 27721853349',
                    ],
                    2 => [
                        "Dear {$user->name},",
                        "Your payment is {$dpd} days overdue. This is your second notice.",
                        'Outstanding: R'.number_format($schedule->emi_amount, 2),
                        'Please make payment immediately or contact us to discuss arrangements.',
                    ],
                    default => [
                        "Dear {$user->name},",
                        'Your payment of R'.number_format($schedule->emi_amount, 2)." is {$dpd} day(s) overdue.",
                        'Please make payment as soon as possible to avoid additional charges.',
                        'Contact us: 27721853349',
                    ],
                };

                try {
                    Mail::to($user->email)->queue(
                        new \App\Mail\LoanNotificationMail($subject, $lines, $application)
                    );
                    $total++;
                } catch (\Exception $e) {
                    Log::warning("Escalation email failed for user #{$user->id}: ".$e->getMessage());
                }
            }
        }

        $this->info("Escalation notices sent: {$total}");

        $labels = [
            0 => '1-'.($stage2Dpd - 1),
            1 => "{$stage2Dpd}-".($secondNoticeDpd - 1),
            2 => "{$secondNoticeDpd}-".($stage3Dpd - 1),
            3 => "{$stage3Dpd}+",
        ];
        foreach ($overdue as $tier => $items) {
            $this->line("  [{$labels[$tier]} DPD]: {$items->count()} accounts");
        }

        return self::SUCCESS;
    }
}
