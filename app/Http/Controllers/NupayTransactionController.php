<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storenupay_transactionRequest;
use App\Http\Requests\Updatenupay_transactionRequest;
use App\Models\AuditLog;
use App\Models\import_batch;
use App\Models\nupay_transaction;
use App\Models\nupay_transactions_staging;
use App\Services\NuPayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NupayTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $nuPayService;

    public function __construct(NuPayService $nuPayService)
    {
        $this->nuPayService = $nuPayService;
    }

    public function index()
    {
        $batches = import_batch::where('source', 'nupay')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.batches.index', compact('batches'));
    }

    /**
     * Show details of a single import batch
     */
    public function show(string $importRef)
    {
        $batch = import_batch::where('import_ref', $importRef)->firstOrFail();

        $transactions = nupay_transactions_staging::where('import_ref', $importRef)->get();

        $feeRate = 0.02;

        /*
        |--------------------------------------------------------------------------
        | Summary grouped by action_date + transaction_type
        |--------------------------------------------------------------------------
        */
        $summary = $transactions
            ->groupBy([
                fn ($t) => \Carbon\Carbon::parse($t->action_date)->format('Y-m'),
                fn ($t) => $t->transaction_type,
            ])
            ->map(function ($types) use ($feeRate) {
                return $types->map(function ($group) use ($feeRate) {
                    $totalAmount = $group->sum('instalment_amount');
                    $totalFee = round($totalAmount * $feeRate, 2);

                    return [
                        'total_amount' => round($totalAmount, 2),
                        'total_fee' => $totalFee,
                        'net_amount' => round($totalAmount - $totalFee, 2),
                        'count' => $group->count(),
                    ];
                });
            });

        // 'tracking' rows are never postable (see post() / postTransaction())
        // — surfaced separately so the "Post to GL" button reflects what will
        // actually be attempted, not the batch's total row count.
        $postableCount = $transactions->whereNull('posted_at')->where('transaction_type', '!=', 'tracking')->count();
        $trackingPendingCount = $transactions->whereNull('posted_at')->where('transaction_type', '=', 'tracking')->count();

        return view('admin.batches.show', compact('batch', 'transactions', 'summary', 'postableCount', 'trackingPendingCount'));
    }

    /**
     * Post all staged transactions for a given import batch
     */
    public function post(Request $request, string $importRef)
    {
        // GL attribution must come from the authenticated session, not a
        // client-editable form field — the hidden `user_id` input used to
        // be trusted as-is, letting the audit trail be spoofed to any ID.
        $userId = $request->user()->id;

        // 'tracking' rows are still-in-flight mandates with no resolved
        // outcome yet — NuPayService::postTransaction() has no handler for
        // them and always throws. Attempting them here would show up as
        // "failed" every time, forever (they never get posted_at set, so
        // they're never actually removed from the unposted queue) — skip
        // them rather than attempt-and-fail the same rows on every click.
        $skippedTracking = nupay_transactions_staging::where('import_ref', $importRef)
            ->whereNull('posted_at')
            ->where('transaction_type', 'tracking')
            ->count();

        $stagedTxns = nupay_transactions_staging::where('import_ref', $importRef)
            ->whereNull('posted_at')
            ->where('transaction_type', '!=', 'tracking')
            ->get();

        if ($stagedTxns->isEmpty()) {
            return back()->with('error', $skippedTracking > 0
                ? "No postable transactions for import_ref {$importRef} — {$skippedTracking} row(s) are still 'tracking' (not yet resolved)."
                : "No unposted transactions for import_ref {$importRef}");
        }

        $summary = [
            'total' => $stagedTxns->count(),
            'success' => 0,
            'failed' => [],
            'skipped_tracking' => $skippedTracking,
        ];

        foreach ($stagedTxns as $txn) {
            try {
                $this->nuPayService->postTransaction($txn->id, $userId);
                $summary['success']++;
            } catch (Exception $e) {
                Log::error("Failed to post NuPay txn #{$txn->id}", [
                    'import_ref' => $importRef,
                    'mandate_id' => $txn->mandate_id,
                    'debtor_id' => $txn->debtor_id,
                    'transaction_type' => $txn->transaction_type,
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $summary['failed'][] = [
                    'txn_id' => $txn->id,
                    'mandate_id' => $txn->mandate_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $msg = "Batch '{$importRef}' posted. Success: {$summary['success']}, Failed: ".count($summary['failed']);
        if ($skippedTracking > 0) {
            $msg .= ", Skipped (tracking, not yet resolved): {$skippedTracking}";
        }

        return redirect()->route('nu-pay.import.index')->with('success', $msg);
    }

    /**
     * Correct a staged (not-yet-posted) NuPay row before it's posted to the
     * GL — the most common case is debtor_id being one digit off from the
     * client's registered ID_Number (bank export typo/OCR error), which
     * otherwise makes NuPayService::postTransaction() fail with "No user
     * found for debtor_id" and leaves the row stuck. Locked once posted —
     * a posted transaction is already reflected in the GL/audit trail and
     * must not be silently rewritten; correcting a posted row means
     * reversing it properly instead.
     */
    public function updateTransaction(Request $request, string $importRef, nupay_transactions_staging $transaction)
    {
        abort_unless($transaction->import_ref === $importRef, 404);
        abort_if($transaction->posted_at !== null, 403, 'This transaction has already been posted to the GL and can no longer be edited.');

        $validated = $request->validate([
            'debtor_id' => 'nullable|string|max:20',
            'debtor_name' => 'nullable|string|max:255',
            'mandate_id' => 'nullable|string|max:50',
            'mandate_request_tran_id' => 'nullable|string|max:100',
            'contract_reference' => 'nullable|string|max:100',
            'instalment_amount' => 'nullable|numeric|min:0',
            'action_date' => 'nullable|date',
            'transaction_type' => 'nullable|in:success,failed,canceled,reversed,tracking',
        ]);

        $old = $transaction->only(array_keys($validated));

        $transaction->update($validated);

        AuditLog::record(
            'nupay_staging_correction',
            $transaction,
            $old,
            $validated,
            'Manually corrected before posting to GL.'
        );

        return back()->with('success', "Transaction #{$transaction->id} updated.");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Storenupay_transactionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(nupay_transaction $nupay_transaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Updatenupay_transactionRequest $request, nupay_transaction $nupay_transaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(nupay_transaction $nupay_transaction)
    {
        //
    }
}
