<?php

namespace App\Services\Reporting;

use App\Models\AccountSnapshot;
use App\Models\LendingSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Materialises one flat reporting_account_snapshots row per open loan for a
 * given reporting month. Every derived value (DPD, arrears, payment profile,
 * account status) is computed here so downstream export profiles can select
 * columns with zero calculation.
 *
 * As-of-date: every figure is computed as at the LAST DAY of $period, not
 * now(). A re-run or backfill of a prior month therefore returns that month's
 * numbers — required because export runs may re-send a period. Balances that
 * genuinely cannot be reconstructed for an old month (remaining_balance has no
 * history table) are left null for periods older than last month.
 *
 * Delinquency nets payments off oldest-first, so a borrower a few rand short on
 * every instalment reports a few rand in arrears — NOT "first instalment still
 * pending => 300 DPD". That over-reporting bug is the reason this does not
 * simply reuse BadDebtProvisionService::daysOverdue().
 *
 * Callers are responsible for the POPIA / feature-flag gate
 * (config('exports.snapshots_enabled')) — the scheduled command enforces it;
 * this service does not, so tests can exercise it directly.
 */
class AccountSnapshotService
{
    /** Loan statuses that must never be reported: unwound or never funded. */
    private const EXCLUDED_STATUSES = ['reversed', 'pending', 'approved', 'rejected'];

    /**
     * @return array{period:string, generation:int, rows:int}
     *
     * @throws \InvalidArgumentException on a malformed period
     */
    public function buildForPeriod(string $period, ?int $generation = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new \InvalidArgumentException("Invalid period '{$period}'. Use YYYY-MM.");
        }

        $periodEnd = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->startOfDay();
        $generation ??= $this->resolveGeneration($period);

        $defaultDpd = (int) config('exports.default_dpd_threshold', 90);
        $settings = LendingSetting::current();

        // Populate live remaining_balance only for the current or previous
        // calendar month — older months have no balance history to reconstruct.
        $balanceReconstructable = $periodEnd->gte(now()->subMonthNoOverflow()->startOfMonth());

        $rows = 0;

        DB::transaction(function () use (
            $period, $generation, $periodEnd, $defaultDpd, $balanceReconstructable, &$rows
        ) {
            AccountSnapshot::where('period', $period)->where('generation', $generation)->delete();

            $this->eligibleLoansQuery($periodEnd)
                ->orderBy('l.id')
                ->chunk(500, function (Collection $loans) use (
                    $period, $generation, $periodEnd, $defaultDpd, $balanceReconstructable, &$rows
                ) {
                    $appIds = $loans->pluck('loan_application_id')->filter()->unique()->values()->all();
                    $loanIds = $loans->pluck('loan_id')->all();

                    $schedules = $this->schedulesByApp($appIds, $periodEnd);
                    $scheduleBounds = $this->scheduleBoundsByApp($appIds);
                    [$paidBySchedule, $paymentsByLoan] = $this->paymentsForChunk($loanIds, $periodEnd);
                    $recoveries = $this->activeRecoveries($loanIds);
                    $disputes = $this->openDisputes($loanIds);
                    $priorByLoan = $this->priorSnapshots($loanIds, $period);

                    $insert = [];

                    foreach ($loans as $loan) {
                        $insert[] = $this->buildRow(
                            $loan, $period, $generation, $periodEnd, $defaultDpd, $balanceReconstructable,
                            $schedules->get($loan->loan_application_id, collect()),
                            $scheduleBounds->get($loan->loan_application_id),
                            $paidBySchedule,
                            $paymentsByLoan->get($loan->loan_id, collect()),
                            $recoveries->get($loan->loan_id),
                            $disputes->get($loan->loan_id),
                            $priorByLoan->get($loan->loan_id),
                        );
                    }

                    foreach (array_chunk($insert, 200) as $batch) {
                        DB::table('reporting_account_snapshots')->insert($batch);
                    }
                    $rows += count($insert);
                });
        });

        return ['period' => $period, 'generation' => $generation, 'rows' => $rows];
    }

    // ── Generation resolution ────────────────────────────────────────────────

    /**
     * Reuse the newest generation if nothing has consumed it yet (a plain
     * rebuild); otherwise start a new immutable generation.
     */
    private function resolveGeneration(string $period): int
    {
        $maxGen = (int) AccountSnapshot::where('period', $period)->max('generation');

        if ($maxGen === 0) {
            return 1;
        }

        $consumed = Schema::hasTable('export_runs')
            && DB::table('export_runs')
                ->where('period', $period)
                ->where('snapshot_generation', $maxGen)
                ->whereIn('status', ['built', 'success'])
                ->exists();

        return $consumed ? $maxGen + 1 : $maxGen;
    }

    // ── Source queries ──────────────────────────────────────────────────────

    private function eligibleLoansQuery(Carbon $periodEnd)
    {
        return DB::table('loans as l')
            ->join('loan_applications as la', 'la.id', '=', 'l.loan_application_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'u.id')
            ->leftJoin('loan_fees as lf', 'lf.loan_application_id', '=', 'l.loan_application_id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'l.loan_product_id')
            ->whereNotNull('l.disbursed_date')
            ->whereDate('l.disbursed_date', '<=', $periodEnd)
            ->whereNotIn('l.status', self::EXCLUDED_STATUSES)
            ->select([
                'l.id as loan_id', 'l.loan_application_id', 'l.user_id',
                'l.status as loan_status_raw', 'l.disbursed_date', 'l.loan_type',
                'l.principal_amount', 'l.total_interest', 'l.total_fees_excl_vat',
                'l.total_vat', 'l.total_amount_due', 'l.remaining_balance',
                'l.loan_term_months', 'l.carried_forward_shortfall',
                'l.written_off_date', 'l.write_off_amount',
                'c.id as customer_id', 'c.customer_code',
                'u.name as borrower_name', 'u.ID_Number as id_number',
                'u.date_of_birth', 'u.gender', 'u.nationality',
                'u.address as address_raw', 'u.phone', 'u.email',
                'la.nca_credit_type', 'la.ncr_purpose_code',
                'lp.code as product_code',
            ]);
    }

    /** Non-deleted schedules due on or before the period end, grouped by loan_application_id. */
    private function schedulesByApp(array $appIds, Carbon $periodEnd): Collection
    {
        if (empty($appIds)) {
            return collect();
        }

        return DB::table('repayment_schedules')
            ->whereNull('deleted_at')
            ->whereIn('loan_id', $appIds)
            ->whereDate('due_date', '<=', $periodEnd)
            ->orderBy('due_date')->orderBy('id')
            ->get(['id', 'loan_id', 'emi_amount', 'due_date', 'paid_at', 'status'])
            ->groupBy('loan_id');
    }

    /** MIN/MAX schedule dates across ALL schedules (not just due-by-period), per loan_application_id. */
    private function scheduleBoundsByApp(array $appIds): Collection
    {
        if (empty($appIds)) {
            return collect();
        }

        return DB::table('repayment_schedules')
            ->whereNull('deleted_at')
            ->whereIn('loan_id', $appIds)
            ->groupBy('loan_id')
            ->get([
                DB::raw('loan_id'),
                DB::raw('MIN(due_date) as first_due'),
                DB::raw('MAX(due_date) as last_due'),
                DB::raw('MAX(paid_at) as last_paid_at'),
            ])
            ->keyBy('loan_id');
    }

    /**
     * @return array{0: array<int,float>, 1: \Illuminate\Support\Collection} paid-by-schedule sum, payments-by-loan (asc)
     */
    private function paymentsForChunk(array $loanIds, Carbon $periodEnd): array
    {
        $paidBySchedule = [];
        $byLoan = collect();

        DB::table('loan_repayments')
            ->whereIn('loan_id', $loanIds)
            ->whereDate('payment_date', '<=', $periodEnd)
            ->whereIn('status', ['paid', 'partial'])
            ->orderBy('payment_date')->orderBy('id')
            ->get(['loan_id', 'repayment_schedule_id', 'payment_date', 'payment_amount'])
            ->each(function ($p) use (&$paidBySchedule, $byLoan) {
                if ($p->repayment_schedule_id) {
                    $paidBySchedule[$p->repayment_schedule_id] =
                        ($paidBySchedule[$p->repayment_schedule_id] ?? 0) + (float) $p->payment_amount;
                }
                $bucket = $byLoan->get($p->loan_id, collect());
                $bucket->push($p);
                $byLoan->put($p->loan_id, $bucket);
            });

        return [$paidBySchedule, $byLoan];
    }

    private function activeRecoveries(array $loanIds): Collection
    {
        return DB::table('debt_recoveries')
            ->whereIn('loan_id', $loanIds)
            ->whereIn('status', ['open', 'partial', 'legal'])
            ->pluck('status', 'loan_id');
    }

    private function openDisputes(array $loanIds): Collection
    {
        return DB::table('credit_report_disputes')
            ->whereIn('loan_id', $loanIds)
            ->where('status', 'open')
            ->orderBy('id')
            ->get(['loan_id', 'reference', 'reason'])
            ->keyBy('loan_id');
    }

    /** Newest snapshot strictly before this period, per loan (for profile carry-forward). */
    private function priorSnapshots(array $loanIds, string $period): Collection
    {
        return AccountSnapshot::whereIn('loan_id', $loanIds)
            ->where('period', '<', $period)
            ->orderBy('period', 'desc')->orderBy('generation', 'desc')
            ->get(['loan_id', 'period', 'payment_profile_24m', 'first_default_date', 'principal_at_default', 'current_balance'])
            ->unique('loan_id')
            ->keyBy('loan_id');
    }

    // ── Row assembly ────────────────────────────────────────────────────────

    private function buildRow(
        object $loan, string $period, int $generation, Carbon $periodEnd, int $defaultDpd, bool $balanceReconstructable,
        Collection $schedules, ?object $bounds, array $paidBySchedule, Collection $payments,
        ?string $recoveryStatus, ?object $dispute, ?object $prior
    ): array {
        $status = $loan->loan_status_raw;
        $isWrittenOff = $status === 'written_off';
        $isSettled = in_array($status, ['settled', 'closed'], true);

        // Freeze delinquency at the write-off date for written-off loans.
        $asOf = $periodEnd;
        if ($isWrittenOff && $loan->written_off_date) {
            $woDate = Carbon::parse($loan->written_off_date)->startOfDay();
            $asOf = $woDate->lt($periodEnd) ? $woDate : $periodEnd;
        }

        $termMonths = (int) ($loan->loan_term_months ?: 0);
        $totalAmountDue = (float) $loan->total_amount_due;
        $instalment = $termMonths > 0 && $totalAmountDue > 0
            ? round($totalAmountDue / $termMonths, 2)
            : (float) optional($schedules->first())->emi_amount;

        $del = ($isSettled)
            ? ['arrears' => 0.0, 'instalments' => 0, 'dpd' => 0]
            : $this->computeDelinquency($schedules, $paidBySchedule, $asOf);

        $accountStatus = match (true) {
            $isWrittenOff => 'WrittenOff',
            $isSettled => 'Closed',
            $recoveryStatus !== null => 'Legal',
            $del['dpd'] > 0 => 'Delinquent',
            default => 'Open',
        };

        $profileChar = $this->profileChar($accountStatus, $del['dpd']);
        $profile = substr($profileChar.($prior->payment_profile_24m ?? ''), 0, 24);
        $profile = str_pad($profile, 24, 'U');

        // first_default_date: carried forward if already set, else stamped the
        // first period DPD crosses the default threshold on an open account.
        $firstDefaultDate = $prior->first_default_date ?? null;
        $principalAtDefault = $prior->principal_at_default ?? null;
        if (! $firstDefaultDate && ! $isSettled && $del['dpd'] >= $defaultDpd) {
            $firstDefaultDate = $periodEnd->toDateString();
            $principalAtDefault = (float) $loan->principal_amount; // contractual principal (documented approximation)
        }

        $lastPayment = $payments->last();
        $dob = $loan->date_of_birth ? Carbon::parse($loan->date_of_birth) : null;

        $currentBalance = $balanceReconstructable ? (float) $loan->remaining_balance : null;
        if ($isWrittenOff) {
            $currentBalance = 0.0;
        }

        $now = now();

        $data = [
            'period' => $period,
            'generation' => $generation,
            'loan_id' => $loan->loan_id,
            'loan_application_id' => $loan->loan_application_id,
            'user_id' => $loan->user_id,
            'customer_id' => $loan->customer_id,
            'account_no' => 'KL-'.str_pad((string) $loan->loan_id, 6, '0', STR_PAD_LEFT),

            'borrower_name' => $loan->borrower_name,
            'id_number' => $loan->id_number,
            'date_of_birth' => $loan->date_of_birth,
            'gender' => $loan->gender,
            'nationality' => $loan->nationality,
            'borrower_age' => $dob ? $dob->diffInYears($periodEnd) : null,

            'address_raw' => $loan->address_raw,
            'phone' => $loan->phone,
            'email' => $loan->email,

            'nca_credit_type' => $loan->nca_credit_type,
            'ncr_purpose_code' => $loan->ncr_purpose_code,
            'loan_type' => $loan->loan_type,
            'product_code' => $loan->product_code,
            'term_months' => $termMonths ?: null,

            'principal_amount' => (float) $loan->principal_amount,
            'total_interest' => (float) $loan->total_interest,
            'total_fees' => (float) $loan->total_fees_excl_vat,
            'total_vat' => (float) $loan->total_vat,
            'total_amount_due' => $totalAmountDue,
            'instalment_amount' => $instalment ?: null,
            'opening_balance' => $prior->current_balance ?? null,
            'current_balance' => $currentBalance,
            'arrears_amount' => $del['arrears'],
            'last_payment_amount' => $lastPayment ? (float) $lastPayment->payment_amount : null,
            'write_off_amount' => (float) $loan->write_off_amount,
            'carried_forward_shortfall' => (float) $loan->carried_forward_shortfall,
            'principal_at_default' => $principalAtDefault,

            'disbursed_date' => $loan->disbursed_date,
            'first_instalment_date' => $bounds->first_due ?? null,
            'final_instalment_date' => $bounds->last_due ?? null,
            'last_payment_date' => $lastPayment->payment_date ?? null,
            'settled_date' => $isSettled ? ($bounds->last_paid_at ? Carbon::parse($bounds->last_paid_at)->toDateString() : null) : null,
            'written_off_date' => $loan->written_off_date,
            'first_default_date' => $firstDefaultDate,

            'days_past_due' => $del['dpd'],
            'instalments_in_arrears' => $del['instalments'],
            'months_in_arrears' => $instalment > 0 ? (int) round($del['arrears'] / $instalment) : intdiv($del['dpd'], 30),
            'ifrs9_stage' => $isSettled ? null : $this->ifrs9Stage($del['dpd']),
            'payment_status_code' => 'KL:'.$profileChar,
            'payment_profile_24m' => $profile,

            'account_status' => $accountStatus,
            'loan_status_raw' => $status,
            'under_dispute' => $dispute !== null,
            'dispute_reference' => $dispute ? ($dispute->reference ?: 'DISPUTE-'.$loan->loan_id) : null,

            'snapshot_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $data['source_hash'] = hash('sha256', json_encode([
            $data['account_no'], $data['current_balance'], $data['arrears_amount'],
            $data['days_past_due'], $data['account_status'], $data['under_dispute'],
        ]));

        return $data;
    }

    /**
     * Arrears = Σ(instalments due by asOf) − Σ(payments), payments allocated
     * oldest-first. DPD is measured from the oldest instalment not yet fully
     * covered by that allocation.
     *
     * @return array{arrears:float, instalments:int, dpd:int}
     */
    private function computeDelinquency(Collection $schedules, array $paidBySchedule, Carbon $asOf): array
    {
        $due = $schedules
            ->filter(fn ($s) => Carbon::parse($s->due_date)->startOfDay()->lte($asOf))
            ->values();

        if ($due->isEmpty()) {
            return ['arrears' => 0.0, 'instalments' => 0, 'dpd' => 0];
        }

        $totalDue = 0.0;
        $totalPaid = 0.0;
        foreach ($due as $s) {
            $totalDue += (float) $s->emi_amount;
            $totalPaid += (float) ($paidBySchedule[$s->id] ?? 0);
        }

        $allocated = min($totalPaid, $totalDue);
        $arrears = round(max(0.0, $totalDue - $allocated), 2);

        if ($arrears <= 0.009) {
            return ['arrears' => 0.0, 'instalments' => 0, 'dpd' => 0];
        }

        $remaining = $allocated;
        $dpd = 0;
        $instalments = 0;
        foreach ($due as $i => $s) {
            $emi = (float) $s->emi_amount;
            if ($remaining >= $emi - 0.01) {
                $remaining -= $emi;

                continue;
            }
            // First instalment not fully covered — the oldest arrears item.
            $instalments = $due->count() - $i;
            $dueDate = Carbon::parse($s->due_date)->startOfDay();
            $dpd = $dueDate->lt($asOf) ? $dueDate->diffInDays($asOf) : 0;
            break;
        }

        return ['arrears' => $arrears, 'instalments' => $instalments, 'dpd' => (int) $dpd];
    }

    private function ifrs9Stage(int $dpd): int
    {
        $s = LendingSetting::current();

        return match (true) {
            $dpd >= $s->ifrs9_stage3_dpd => 3,
            $dpd >= $s->ifrs9_stage2_dpd => 2,
            default => 1,
        };
    }

    /** One-character payment-profile bucket for a month. Interim scheme, KL-prefixed elsewhere. */
    private function profileChar(string $accountStatus, int $dpd): string
    {
        return match ($accountStatus) {
            'WrittenOff' => 'W',
            'Closed' => 'C',
            'Legal' => 'L',
            default => match (true) {
                $dpd <= 0 => '0',
                $dpd < 30 => '1',
                $dpd < 60 => '2',
                $dpd < 90 => '3',
                $dpd < 120 => '4',
                $dpd < 150 => '5',
                $dpd < 180 => '6',
                default => '9',
            },
        };
    }
}
