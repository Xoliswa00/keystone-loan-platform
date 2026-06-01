<?php

namespace App\Services;

use App\Models\{
    arbatch,
    glbatch,
    glentry,
    glpost,
    gl_accounts
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class GLPostingService
{
    /**
     * Fully post an approved AR batch into the General Ledger
     *
     * Flow:
     * AR Batch → GL Batch → GL Entries → GL Post → Account Balances
     */
    public function postArBatch(arbatch $arBatch, int $userId): glbatch
    {
        return DB::transaction(function () use ($arBatch, $userId) {

            if ($arBatch->status === 'posted') {
                throw new Exception('AR batch already posted');
            }

            if ($arBatch->entries->isEmpty()) {
                throw new Exception('AR batch has no entries');
            }

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Create GL Batch (Journal Header)
            |--------------------------------------------------------------------------
            */
            $glBatch = glbatch::create([
                'reference'   => 'GLB-' . now()->format('YmdHis') . '-' . $arBatch->id,
                'source_type' => $arBatch->source_type,
                'source_id'   => $arBatch->source_id,
                'status'      => 'posted',
                'created_by'  => $userId,
                'posted_at'   => now(),
            ]);

            $totalDebit  = 0;
            $totalCredit = 0;

            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Create GL Entries + Ledger Posts + Update Balances
            |--------------------------------------------------------------------------
            */
            foreach ($arBatch->entries as $arEntry) {

                $debit  = $arEntry->entry_type === 'debit'  ? $arEntry->amount : 0;
                $credit = $arEntry->entry_type === 'credit' ? $arEntry->amount : 0;

                $totalDebit  += $debit;
                $totalCredit += $credit;

                // GL Entry (journal line)
                $glEntry = glentry::create([
                    'batch_id'    => $glBatch->id,
                    'account_id'  => $arEntry->gl_account_id,
                    'debit'       => $debit,
                    'credit'      => $credit,
                    'description' => $arEntry->description,
                ]);

                // Lock GL account
                $account = gl_accounts::lockForUpdate()
                    ->findOrFail($arEntry->gl_account_id);

                // GL Post (ledger line — THIS is authoritative)
                glpost::create([
                    'entry_id'   => $glEntry->id,
                    'account_id' => $account->id,
                    'debit'      => $debit,
                    'credit'     => $credit,
                    'post_date'  => now()->toDateString(),
                    'reference'  => $glBatch->reference,
                    'module'     => $glBatch->source_type,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Ledger balance rule (simple model)
                |--------------------------------------------------------------------------
                | Debit  → increases balance
                | Credit → decreases balance
                |
                | Assets/Expenses will be positive
                | Income/Liabilities will trend negative (expected for now)
                */
                $account->current_balance =
                    $account->current_balance
                    + $debit
                    - $credit;

                $account->save();
            }

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Enforce balancing rule
            |--------------------------------------------------------------------------
            */
            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new Exception('GL batch not balanced');
            }

            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Mark AR Batch as Posted
            |--------------------------------------------------------------------------
            */
            $arBatch->update([
                'status'       => 'posted',
                'posted_to_gl' => true,
                'posted_at'    => now(),
            ]);

            Log::info('AR batch fully posted to GL', [
                'ar_batch_id' => $arBatch->id,
                'gl_batch_id' => $glBatch->id,
                'debit'       => $totalDebit,
                'credit'      => $totalCredit,
            ]);

            return $glBatch;
        });
    }
}
