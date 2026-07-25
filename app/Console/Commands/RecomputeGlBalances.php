<?php

namespace App\Console\Commands;

use App\Models\gl_accounts;
use App\Services\GLPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeGlBalances extends Command
{
    protected $signature = 'keystone:recompute-gl-balances {--apply : Write the corrected balances (default is a dry-run report only)}';

    /**
     * gl_accounts.current_balance is a persisted running total updated
     * incrementally by GLPostingService on every post — it is not derived
     * per-report. Before GLPostingService::resolveBalanceDirection() existed,
     * balance direction was resolved from account_type (a granular subtype
     * that never literally equals 'asset'/'liability'/etc.), so every real
     * asset account had every debit/credit applied backwards. Any balance
     * accumulated under that bug is wrong and stays wrong until replayed —
     * this command re-derives current_balance from full glentries history
     * using the corrected direction logic, rather than trying to patch the
     * running total in place.
     */
    protected $description = 'Recompute gl_accounts.current_balance from glentries history using the corrected debit/credit direction logic.';

    public function handle(GLPostingService $gl): int
    {
        $apply = (bool) $this->option('apply');
        $accounts = gl_accounts::with('chartOfAccount')->get();

        $rows = [];
        $changed = 0;

        foreach ($accounts as $account) {
            $sums = DB::table('glentries')
                ->where('account_id', $account->id)
                ->selectRaw('COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
                ->first();

            $direction = $gl->resolveBalanceDirection($account);
            $movement = $direction === 'liability' || in_array($direction, ['income', 'equity'], true)
                ? ((float) $sums->credit - (float) $sums->debit)
                : ((float) $sums->debit - (float) $sums->credit);

            $correct = round((float) $account->opening_balance + $movement, 2);
            $current = round((float) $account->current_balance, 2);

            if ($correct !== $current) {
                $changed++;
                $rows[] = [
                    $account->chartOfAccount->account_code ?? $account->gl_code,
                    $account->chartOfAccount->account_group ?? '—',
                    $account->chartOfAccount->account_type ?? '—',
                    number_format($current, 2),
                    number_format($correct, 2),
                ];

                if ($apply) {
                    $account->current_balance = $correct;
                    $account->save();
                }
            }
        }

        if (empty($rows)) {
            $this->info('All gl_accounts.current_balance values already match their glentries history — nothing to fix.');

            return self::SUCCESS;
        }

        $this->table(['Account', 'Group', 'Type', 'Current (wrong)', 'Recomputed (correct)'], $rows);

        if ($apply) {
            $this->info("Applied corrected balances to {$changed} account(s).");
        } else {
            $this->warn("{$changed} account(s) need correction. Re-run with --apply to write the fix.");
        }

        return self::SUCCESS;
    }
}
