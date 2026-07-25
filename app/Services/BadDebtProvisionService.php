<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\gl_accounts;
use App\Models\glmapping;
use App\Models\LendingSetting;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadDebtProvisionService
{
    protected GLPostingService $glPosting;

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Run provisioning for ALL active loans (call from a scheduled command)
    // ──────────────────────────────────────────────────────────────────────────

    public function runMonthlyProvisioning(int $adminId): array
    {
        $loans = Loan::whereIn('status', ['disbursed', 'payment_failed'])->get();
        $results = ['processed' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($loans as $loan) {
            try {
                $this->provisionLoan($loan, $adminId);
                $results['processed']++;
            } catch (Exception $e) {
                Log::error("Provisioning failed for loan #{$loan->id}: ".$e->getMessage());
                $results['errors'][] = "Loan #{$loan->id}: ".$e->getMessage();
                $results['skipped']++;
            }
        }

        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Provision a single loan
    // ──────────────────────────────────────────────────────────────────────────

    public function provisionLoan(Loan $loan, int $adminId): ?\App\Models\BadDebtProvision
    {
        return DB::transaction(function () use ($loan, $adminId) {
            // lockForUpdate(): without it, two concurrent provisioning runs
            // for the same loan both read the same stale prior provision and
            // both post the same movement to GL, doubling the expense/
            // allowance for one loan.
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);

            $settings = LendingSetting::current();
            $dpd = $this->daysOverdue($loan);
            $stage = $this->ifrs9Stage($dpd);
            $rate = (float) match ($stage) {
                1 => $settings->ifrs9_stage1_rate,
                2 => $settings->ifrs9_stage2_rate,
                3 => $settings->ifrs9_stage3_rate,
            };

            $outstanding = (float) $loan->remaining_balance;
            $newProvision = round($outstanding * $rate, 2);

            // Get prior provision for movement calculation
            $prior = \App\Models\BadDebtProvision::where('loan_id', $loan->id)
                ->orderBy('provision_date', 'desc')
                ->value('provision_amount') ?? 0;

            $movement = round($newProvision - $prior, 2);

            // No change → skip
            if (abs($movement) < 0.01) {
                return null;
            }

            // ── GL entries ──────────────────────────────────────────────────────
            $branchId = $loan->branch_id ?? 1;
            $locationCode = $loan->location_code ?? '000';

            $creditLossGl = $this->resolveGl('credit_loss_expense', $branchId, $locationCode);
            $provisionAccGl = $this->resolveGl('allowance_credit_losses', $branchId, $locationCode);

            if (! $creditLossGl || ! $provisionAccGl) {
                throw new Exception('Missing GL accounts for bad debt provisioning.');
            }

            $customer = $loan->user->customer;
            $ref = 'ARB-PROV-'.now()->format('YmdHis').'-'.$loan->id;

            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => Loan::class,
                'source_id' => $loan->id,
                'total_amount' => abs($movement),
                'status' => 'approved',
                'created_by' => $adminId,
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            if ($movement > 0) {
                // Increase provision: Dr Credit Loss Expense / Cr Allowance
                $entries = [
                    $this->entry($arBatch->id, $creditLossGl->id, 'debit', $movement,
                        "Credit loss expense — loan #{$loan->id} Stage {$stage} DPD {$dpd}"),
                    $this->entry($arBatch->id, $provisionAccGl->id, 'credit', $movement,
                        "Allowance for credit losses — loan #{$loan->id}"),
                ];
            } else {
                // Decrease provision (recovery or improvement)
                $decrease = abs($movement);
                $entries = [
                    $this->entry($arBatch->id, $provisionAccGl->id, 'debit', $decrease,
                        "Provision release — loan #{$loan->id} Stage {$stage}"),
                    $this->entry($arBatch->id, $creditLossGl->id, 'credit', $decrease,
                        "Credit loss reversal — loan #{$loan->id}"),
                ];
            }

            arbatch_entries::insert($entries);
            $glBatch = $this->glPosting->postArBatch($arBatch, $adminId);
            $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

            // ── Save provision record ────────────────────────────────────────────
            $provision = \App\Models\BadDebtProvision::create([
                'loan_id' => $loan->id,
                'user_id' => $loan->user_id,
                'ifrs9_stage' => $stage,
                'days_past_due' => $dpd,
                'outstanding_balance' => $outstanding,
                'provision_rate' => $rate,
                'provision_amount' => $newProvision,
                'prior_provision' => $prior,
                'provision_movement' => $movement,
                'gl_batch_reference' => $ref,
                'gl_posted' => true,
                'created_by' => $adminId,
                'provision_date' => now()->toDateString(),
                'notes' => "Stage {$stage} — {$dpd} DPD — Rate ".($rate * 100).'%',
            ]);

            Log::info("Provision created for loan #{$loan->id}", [
                'stage' => $stage, 'dpd' => $dpd, 'amount' => $newProvision, 'movement' => $movement,
            ]);

            // ── Auto write-off check ─────────────────────────────────────────────
            $writeOffDpd = $settings->ifrs9_writeoff_dpd;
            if ($dpd >= $writeOffDpd && $loan->status !== 'written_off') {
                $this->writeOff($loan, $adminId, "Auto write-off — {$dpd} DPD exceeded {$writeOffDpd} day threshold.");
            }

            return $provision;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Write-off a loan
    // ──────────────────────────────────────────────────────────────────────────

    public function writeOff(Loan $loan, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($loan, $adminId, $reason) {

            $outstanding = (float) $loan->remaining_balance;
            if ($outstanding <= 0) {
                return;
            }

            $branchId = $loan->branch_id ?? 1;
            $locationCode = $loan->location_code ?? '000';

            $provisionAccGl = $this->resolveGl('allowance_credit_losses', $branchId, $locationCode);
            $loanReceivableGl = $this->resolveGl('loan_disbursement_dr', $branchId, $locationCode);

            if (! $provisionAccGl || ! $loanReceivableGl) {
                throw new Exception('Missing GL accounts for write-off.');
            }

            // Prior accumulated provision for this loan
            $totalProvision = \App\Models\BadDebtProvision::where('loan_id', $loan->id)
                ->sum('provision_movement');
            $totalProvision = max(0, round($totalProvision, 2));

            $unprovisioned = max(0, $outstanding - $totalProvision);

            $customer = $loan->user->customer;
            $ref = 'ARB-WO-'.now()->format('YmdHis').'-'.$loan->id;

            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => Loan::class,
                'source_id' => $loan->id,
                'total_amount' => $outstanding,
                'status' => 'approved',
                'created_by' => $adminId,
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            $entries = [];

            // Dr Allowance (use up the provision)
            if ($totalProvision > 0) {
                $entries[] = $this->entry($arBatch->id, $provisionAccGl->id, 'debit', $totalProvision,
                    "Write-off: use accumulated provision — loan #{$loan->id}");
            }

            // Dr Credit Loss Expense (any unprovisioned balance)
            if ($unprovisioned > 0) {
                $creditLossGl = $this->resolveGl('credit_loss_expense', $branchId, $locationCode);
                if ($creditLossGl) {
                    $entries[] = $this->entry($arBatch->id, $creditLossGl->id, 'debit', $unprovisioned,
                        "Write-off: unprovisioned amount — loan #{$loan->id}");
                }
            }

            // Cr Loans Receivable (remove the asset)
            $entries[] = $this->entry($arBatch->id, $loanReceivableGl->id, 'credit', $outstanding,
                "Write-off: loans receivable — loan #{$loan->id}");

            arbatch_entries::insert($entries);
            $this->glPosting->postArBatch($arBatch, $adminId);
            $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

            // Mark loan written off
            $loan->update([
                'status' => 'written_off',
                'remaining_balance' => 0,
                'written_off_date' => now()->toDateString(),
                'write_off_amount' => $outstanding,
                'written_off_by' => $adminId,
                'write_off_reason' => $reason,
            ]);

            $customer->decrement('current_balance', $outstanding);

            // Auto-open a recovery case — write-off doesn't mean stop chasing
            $existingCase = \App\Models\DebtRecovery::where('loan_id', $loan->id)
                ->whereNotIn('status', ['recovered', 'closed'])
                ->first();

            if (! $existingCase) {
                \App\Models\DebtRecovery::create([
                    'loan_id' => $loan->id,
                    'user_id' => $loan->user_id,
                    'status' => 'open',
                    'original_write_off_amount' => $outstanding,
                    'total_recovered' => 0,
                    'outstanding_recovery' => $outstanding,
                    'notes' => "Auto-opened on write-off: {$reason}",
                    'opened_at' => now()->toDateString(),
                    'next_action_date' => now()->addDays(14)->toDateString(),
                ]);
            }

            Log::info("Loan #{$loan->id} written off. Amount: R{$outstanding}. Reason: {$reason}");
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IFRS 9 helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function daysOverdue(Loan $loan): int
    {
        $earliest = RepaymentSchedule::where('loan_id', $loan->loan_application_id)
            ->whereIn('status', ['pending', 'payment_failed'])
            ->orderBy('due_date')
            ->value('due_date');

        if (! $earliest) {
            return 0;
        }

        return max(0, now()->diffInDays($earliest, false) * -1);
    }

    public function ifrs9Stage(int $dpd): int
    {
        $settings = LendingSetting::current();

        return match (true) {
            $dpd >= $settings->ifrs9_stage3_dpd => 3,
            $dpd >= $settings->ifrs9_stage2_dpd => 2,
            default => 1,
        };
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function entry(int $batchId, int $accountId, string $type, float $amount, string $desc): array
    {
        return [
            'arbatch_id' => $batchId,
            'gl_account_id' => $accountId,
            'entry_type' => $type,
            'amount' => round($amount, 2),
            'description' => $desc,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function resolveGl(string $key, $branchId = 1, $locationCode = '000'): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();
        if (! $mapping) {
            Log::warning("GL mapping not found: {$key}");

            return null;
        }

        $code = $mapping->account_code;

        return gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
            ->where('branch_id', $branchId)->first()
            ?? gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
                ->where('branch_id', 1)->first();
    }
}
