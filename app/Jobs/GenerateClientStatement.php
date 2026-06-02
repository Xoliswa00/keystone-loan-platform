<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class GenerateClientStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $timeout    = 120;   // 2 minutes max
    public int   $tries      = 2;
    public int   $backoff     = 30;

    protected int    $userId;
    protected string $period;     // YYYY-MM
    protected bool   $emailUser;

    public function __construct(int $userId, string $period = null, bool $emailUser = false)
    {
        $this->userId    = $userId;
        $this->period    = $period ?? now()->format('Y-m');
        $this->emailUser = $emailUser;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $user     = User::findOrFail($this->userId);
        $company  = DB::table('companies')->first();

        // Limit data to avoid memory issues
        $loans = Loan::where('user_id', $user->id)
            ->with(['loanApplication.loanfee', 'repaymentSchedules' => fn($q) => $q->latest()->take(20)])
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

        $filename = 'KCP-Statement-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . $this->period . '.pdf';
        $storagePath = "statements/{$user->id}/{$filename}";

        $pdf = Pdf::loadView('statements.account_statement', [
            'user'             => $user,
            'customer'         => $user->customer,
            'loans'            => $loans,
            'repayments'       => $repayments,
            'totalOutstanding' => $totalOutstanding,
            'nextDue'          => $nextDue,
            'statementDate'    => now()->format('d F Y'),
            'statementRef'     => 'STMT-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . now()->format('Ymd'),
            'company'          => $company,
            'ncr_number'       => $company?->ncr_number ?? 'NCRCP XXXXX',
        ])
        ->setPaper('A4', 'portrait')
        ->setOptions([
            'dpi'                 => 96,    // lower DPI = faster + smaller
            'isHtml5ParserEnabled'=> true,
            'isRemoteEnabled'     => false, // don't fetch remote resources
            'defaultFont'         => 'sans-serif',
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
                        "Your account statement for " . now()->format('F Y') . " is ready.",
                        "Please log in to your dashboard to download it.",
                    ],
                    null
                ));
            } catch (\Exception $e) {
                Log::warning("Statement email failed for user #{$user->id}: " . $e->getMessage());
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Statement generation failed for user #{$this->userId}: " . $exception->getMessage());
        // Clear any in-progress cache key so client can retry
        Cache::forget("statement_generating_{$this->userId}_{$this->period}");
    }
}
