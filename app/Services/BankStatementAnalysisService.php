<?php

namespace App\Services;

use App\Models\CustomerDocument;
use App\Models\CustomerProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Analyses a customer's bank statement CSV to extract:
 *  1. Verified salary amount and payday pattern
 *  2. Balance burn rate — how quickly the balance drops after payday
 *  3. Detected regular debit orders (subscriptions, existing loans, etc.)
 *  4. Risk flag based on days-to-zero after payday
 *
 * This data feeds directly into the affordability assessment:
 *  - Verified income replaces self-declared net salary if higher confidence
 *  - Detected debit orders are added to known monthly expenses
 *  - Days-to-zero < 7 days = very_high risk flag regardless of declared income
 */
class BankStatementAnalysisService
{
    const ZERO_THRESHOLD = 500.00;   // balance below this = "near zero"

    const LOW_PCT_THRESHOLD = 0.20;     // balance below 20% of salary = "low"

    const REGULAR_DEBIT_MONTHS = 2;      // debit must appear in at least 2 months to be "regular"

    /**
     * Run full analysis on a customer's latest bank statements.
     * Reads all unanalysed bank_statement documents for the user.
     */
    public function analyseForUser(int $userId): ?CustomerProfile
    {
        $profile = CustomerProfile::where('user_id', $userId)->first();
        if (! $profile) {
            return null;
        }

        // Get all bank statement documents (multiple months)
        $statements = CustomerDocument::where('user_id', $userId)
            ->where('document_type', 'bank_statement')
            ->whereNull('deleted_at')
            ->orderBy('document_date', 'desc')
            ->take(3) // analyse up to 3 months
            ->get();

        if ($statements->isEmpty()) {
            return null;
        }

        $allTransactions = collect();

        foreach ($statements as $doc) {
            $path = Storage::disk('local')->path($doc->file_path);
            if (! file_exists($path)) {
                continue;
            }

            $rows = $this->parseCsv($path);
            if ($rows->isNotEmpty()) {
                $allTransactions = $allTransactions->merge($rows);
                $doc->update(['analysis_complete' => true]);
            }
        }

        if ($allTransactions->isEmpty()) {
            return null;
        }

        // Run analysis
        $salary = $this->detectSalary($allTransactions);
        $burnRate = $this->calcBurnRate($allTransactions, $salary);
        $debits = $this->detectRegularDebits($allTransactions);
        $riskFlag = $this->riskFlag($burnRate['avg_days_to_zero'], $burnRate['avg_daily_spend'], $salary['amount'] ?? 0);

        // Update profile
        $profile->update([
            'verified_income_amount' => $salary['amount'],
            'verified_income_date_pattern' => $salary['day_of_month'],
            'avg_days_to_zero' => $burnRate['avg_days_to_zero'],
            'avg_days_to_low' => $burnRate['avg_days_to_low'],
            'avg_daily_spend_post_payday' => $burnRate['avg_daily_spend'],
            'detected_regular_debits' => $debits->sum('amount'),
            'detected_debit_count' => $debits->count(),
            'detected_debit_orders' => $debits->values()->toJson(),
            'bank_statement_risk_flag' => $riskFlag['flag'],
            'bank_statement_risk_reason' => $riskFlag['reason'],
            'bank_analysis_run_at' => now(),
        ]);

        // If verified income > declared income, update net_monthly_income
        if ($salary['amount'] > 0 && $salary['amount'] > ($profile->net_monthly_income ?? 0)) {
            $profile->update(['net_monthly_income' => $salary['amount']]);
        }

        // Add detected regular debits to existing_debt expense if not already captured
        if ($debits->sum('amount') > ($profile->expense_existing_debt ?? 0)) {
            $profile->update(['expense_existing_debt' => $debits->sum('amount')]);
        }

        $profile->recalculate();

        return $profile->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Salary detection
    // ──────────────────────────────────────────────────────────────────────────

    protected function detectSalary(Collection $txns): array
    {
        // Salary = largest consistent monthly credit
        // Group credits by month, find the largest credit each month
        $monthlySalaries = $txns
            ->filter(fn ($t) => $t['credit'] > 0)
            ->groupBy(fn ($t) => Carbon::parse($t['date'])->format('Y-m'))
            ->map(fn ($monthTxns) => $monthTxns->sortByDesc('credit')->first());

        if ($monthlySalaries->isEmpty()) {
            return ['amount' => 0, 'day_of_month' => null];
        }

        // Average the top salary credits
        $avgSalary = round($monthlySalaries->avg('credit'), 2);

        // Detect the typical day of month (mode of day numbers)
        $days = $monthlySalaries->map(fn ($t) => Carbon::parse($t['date'])->day);
        $dayMode = $days->groupBy(fn ($d) => $d)->sortByDesc->count()->keys()->first();

        return [
            'amount' => $avgSalary,
            'day_of_month' => $dayMode,
            'months_found' => $monthlySalaries->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Balance burn rate — days to zero after payday
    // ──────────────────────────────────────────────────────────────────────────

    protected function calcBurnRate(Collection $txns, array $salary): array
    {
        if (! $salary['day_of_month'] || $salary['amount'] <= 0) {
            return ['avg_days_to_zero' => null, 'avg_days_to_low' => null, 'avg_daily_spend' => null];
        }

        $payday = $salary['day_of_month'];

        // For each month, track balance from payday for 14 days
        $daysToZeroList = [];
        $daysToLowList = [];
        $dailySpends = [];

        $months = $txns
            ->groupBy(fn ($t) => Carbon::parse($t['date'])->format('Y-m'))
            ->keys();

        foreach ($months as $month) {
            $monthStart = Carbon::createFromFormat('Y-m-d', $month.'-'.str_pad($payday, 2, '0', STR_PAD_LEFT));

            // Find balance on payday
            $paydayTxn = $txns
                ->filter(fn ($t) => Carbon::parse($t['date'])->format('Y-m') === $month)
                ->filter(fn ($t) => abs(Carbon::parse($t['date'])->day - $payday) <= 2)
                ->sortByDesc('credit')
                ->first();

            if (! $paydayTxn || ! isset($paydayTxn['balance'])) {
                continue;
            }

            $paydayBalance = $paydayTxn['balance'];
            $lowThreshold = $paydayBalance * self::LOW_PCT_THRESHOLD;

            // Track daily balance for 14 days after payday
            $daysToZero = null;
            $daysToLow = null;
            $prevBalance = $paydayBalance;
            $spends = [];

            for ($day = 1; $day <= 14; $day++) {
                $checkDate = $monthStart->copy()->addDays($day)->format('Y-m-d');

                $dayTxns = $txns->filter(fn ($t) => $t['date'] === $checkDate);
                $dayBalance = $dayTxns->sortByDesc('date')->first()['balance'] ?? $prevBalance;

                $daySpend = max(0, $prevBalance - $dayBalance);
                if ($daySpend > 0) {
                    $spends[] = $daySpend;
                }

                if ($daysToZero === null && $dayBalance <= self::ZERO_THRESHOLD) {
                    $daysToZero = $day;
                }
                if ($daysToLow === null && $dayBalance <= $lowThreshold) {
                    $daysToLow = $day;
                }

                $prevBalance = $dayBalance;
            }

            if ($daysToZero !== null) {
                $daysToZeroList[] = $daysToZero;
            }
            if ($daysToLow !== null) {
                $daysToLowList[] = $daysToLow;
            }
            if (! empty($spends)) {
                $dailySpends[] = array_sum($spends) / count($spends);
            }
        }

        return [
            'avg_days_to_zero' => ! empty($daysToZeroList) ? (int) round(array_sum($daysToZeroList) / count($daysToZeroList)) : null,
            'avg_days_to_low' => ! empty($daysToLowList) ? (int) round(array_sum($daysToLowList) / count($daysToLowList)) : null,
            'avg_daily_spend' => ! empty($dailySpends) ? round(array_sum($dailySpends) / count($dailySpends), 2) : null,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Regular debit detection
    // ──────────────────────────────────────────────────────────────────────────

    protected function detectRegularDebits(Collection $txns): Collection
    {
        // Find debits that appear consistently (same description, similar amount, monthly)
        $debits = $txns->filter(fn ($t) => $t['debit'] > 50); // ignore small transactions

        $monthCount = $txns->groupBy(fn ($t) => Carbon::parse($t['date'])->format('Y-m'))->count();

        if ($monthCount < 2) {
            return collect();
        }

        // Group by normalized description
        $grouped = $debits->groupBy(fn ($t) => $this->normalizeDescription($t['description']));

        return $grouped
            ->filter(fn ($group) => $group->count() >= self::REGULAR_DEBIT_MONTHS)
            ->map(fn ($group, $desc) => [
                'description' => $desc,
                'amount' => round($group->avg('debit'), 2),
                'day_of_month' => $group->map(fn ($t) => Carbon::parse($t['date'])->day)->mode()[0] ?? null,
                'occurrences' => $group->count(),
            ])
            ->values();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Risk flag
    // ──────────────────────────────────────────────────────────────────────────

    protected function riskFlag(?int $daysToZero, ?float $dailySpend, float $salary): array
    {
        if ($daysToZero === null) {
            return ['flag' => 'low', 'reason' => 'Insufficient data for burn rate analysis.'];
        }

        return match (true) {
            $daysToZero <= 3 => [
                'flag' => 'very_high',
                'reason' => "Balance drops near zero within {$daysToZero} days of payday — extremely high spend rate.",
            ],
            $daysToZero <= 7 => [
                'flag' => 'high',
                'reason' => "Balance drops near zero within {$daysToZero} days of payday — limited capacity for instalment debit.",
            ],
            $daysToZero <= 14 => [
                'flag' => 'medium',
                'reason' => "Balance drops near zero within {$daysToZero} days of payday — monitor closely.",
            ],
            default => [
                'flag' => 'low',
                'reason' => "Balance maintained for {$daysToZero}+ days after payday — adequate financial buffer.",
            ],
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function parseCsv(string $path): Collection
    {
        $handle = fopen($path, 'r');
        $headers = null;
        $rows = collect();

        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (empty(array_filter($line, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim($h)), $line);

                continue;
            }

            $row = array_combine($headers, array_pad(array_slice($line, 0, count($headers)), count($headers), null));

            $date = $this->findVal($row, ['date', 'transaction date', 'value date']);
            $desc = $this->findVal($row, ['description', 'narrative', 'details']);
            $debit = (float) preg_replace('/[^0-9.-]/', '', $this->findVal($row, ['debit', 'debit amount', 'payment']) ?? '0');
            $credit = (float) preg_replace('/[^0-9.-]/', '', $this->findVal($row, ['credit', 'credit amount', 'deposit', 'receipts']) ?? '0');
            $balance = (float) preg_replace('/[^0-9.-]/', '', $this->findVal($row, ['balance', 'running balance']) ?? '0');

            if (! $date || ! $desc) {
                continue;
            }

            try {
                $parsedDate = Carbon::parse($date)->toDateString();
            } catch (\Exception $e) {
                continue;
            }

            $rows->push(['date' => $parsedDate, 'description' => trim($desc), 'debit' => $debit, 'credit' => $credit, 'balance' => $balance]);
        }

        fclose($handle);

        return $rows;
    }

    protected function normalizeDescription(string $desc): string
    {
        // Strip amounts, dates, and reference numbers to find common patterns
        $clean = preg_replace('/\d{6,}/', '', $desc);
        $clean = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}\b/', '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        return strtoupper(trim(substr($clean, 0, 30)));
    }

    protected function findVal(array $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== null && $row[$k] !== '') {
                return $row[$k];
            }
        }

        return null;
    }
}
