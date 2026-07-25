<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateClientStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;   // 2 minutes max

    public int $tries = 2;

    public int $backoff = 30;

    protected int $userId;

    protected string $period;     // YYYY-MM

    protected bool $emailUser;

    public function __construct(int $userId, ?string $period = null, bool $emailUser = false)
    {
        $this->userId = $userId;
        $this->period = $period ?? now()->format('Y-m');
        $this->emailUser = $emailUser;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        $company = DB::table('companies')->first();

        // Limit data to avoid memory issues
        $loans = Loan::where('user_id', $user->id)
            ->with(['loanApplication.loanfee', 'repaymentSchedules' => fn ($q) => $q->latest()->take(20)])
            ->orderBy('created_at', 'desc')
            ->take(10)           // max 10 loans per statement
            ->get();

        $repayments = LoanRepayment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->orderBy('payment_date', 'desc')
            ->take(50)           // max 50 transactions
            ->get();

        $totalOutstanding = $loans->whereIn('status', ['disbursed', 'payment_failed'])->sum('remaining_balance');
        $nextDue = RepaymentSchedule::where('user_id', $user->id)->where('status', 'pending')->orderBy('due_date')->first();
        $ledger = $this->buildLedger($loans, $repayments);

        $filename = 'KCP-Statement-'.str_pad($user->id, 6, '0', STR_PAD_LEFT).'-'.$this->period.'.pdf';
        $storagePath = "statements/{$user->id}/{$filename}";

        $pdf = Pdf::loadView('statements.account_statement', [
            'user' => $user,
            'customer' => $user->customer,
            'loans' => $loans,
            'repayments' => $repayments,
            'totalOutstanding' => $totalOutstanding,
            'nextDue' => $nextDue,
            'ledger' => $ledger,
            'statementDate' => now()->format('d F Y'),
            'statementRef' => 'STMT-'.str_pad($user->id, 6, '0', STR_PAD_LEFT).'-'.now()->format('Ymd'),
            'company' => $company,
            'ncr_number' => $company?->ncr_number ?? 'NCRCP XXXXX',
        ])
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'dpi' => 96,    // lower DPI = faster + smaller
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false, // don't fetch remote resources
                'defaultFont' => 'sans-serif',
            ]);

        // Save to public storage
        Storage::disk('public')->put($storagePath, $pdf->output());

        // Cache the path so the controller can serve it quickly
        Cache::put("statement_{$user->id}_{$this->period}", $storagePath, now()->addHours(24));

        Log::info("Statement generated for user #{$user->id}: {$storagePath}");

        // Email the statement if requested
        if ($this->emailUser && $user->email) {
            try {
                Mail::to($user->email)->send(new \App\Mail\LoanNotificationMail(
                    'Your Statement Is Ready — Keystone Capital Partners',
                    [
                        "Dear {$user->name},",
                        'Your account statement for '.now()->format('F Y').' is ready.',
                        'Please log in to your dashboard to download it.',
                    ],
                    null
                ));
            } catch (\Exception $e) {
                Log::warning("Statement email failed for user #{$user->id}: ".$e->getMessage());
            }
        }
    }

    /**
     * A running debit/credit ledger, like a bank statement — disbursement
     * ("money out": the client now owes it) and each paid repayment
     * ("money in") merged chronologically with a cumulative balance. Neither
     * $loans nor $repayments needed a new query — both were already being
     * fetched above, this just reshapes them for the statement.
     */
    private function buildLedger($loans, $repayments): array
    {
        $events = [];

        foreach ($loans as $loan) {
            if (! $loan->disbursed_date) {
                continue;
            }

            $events[] = [
                'date' => \Carbon\Carbon::parse($loan->disbursed_date),
                'description' => 'Loan Disbursement — Loan #'.str_pad($loan->loan_application_id, 6, '0', STR_PAD_LEFT),
                'out' => (float) ($loan->total_amount_due ?: $loan->loan_amount),
                'in' => 0.0,
            ];
        }

        foreach ($repayments as $repayment) {
            $events[] = [
                'date' => \Carbon\Carbon::parse($repayment->payment_date),
                'description' => 'Repayment — '.ucfirst(str_replace('_', ' ', $repayment->payment_method ?? 'payment')).
                    ($repayment->payment_reference ? ' ('.$repayment->payment_reference.')' : ''),
                'out' => 0.0,
                'in' => (float) $repayment->payment_amount,
            ];
        }

        usort($events, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        $balance = 0.0;
        foreach ($events as &$event) {
            $balance += $event['out'] - $event['in'];
            $event['balance'] = round($balance, 2);
        }

        return $events;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Statement generation failed for user #{$this->userId}: ".$exception->getMessage());
        // Clear any in-progress cache key so client can retry
        Cache::forget("statement_generating_{$this->userId}_{$this->period}");
    }
}
