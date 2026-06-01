<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storenupay_transactionRequest;
use App\Http\Requests\Updatenupay_transactionRequest;
use App\Models\nupay_transaction;
use Illuminate\Http\Request;
use App\Models\nupay_transactions_staging;
use App\Models\import_batch;
use App\Services\NuPayService;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\nupay_transactions;

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
                $totalFee    = round($totalAmount * $feeRate, 2);

                return [
                    'total_amount' => round($totalAmount, 2),
                    'total_fee'    => $totalFee,
                    'net_amount'   => round($totalAmount - $totalFee, 2),
                    'count'        => $group->count(),
                ];
            });
        });


        
        return view('admin.batches.show', compact('batch', 'transactions','summary'));
    }

    /**
     * Post all staged transactions for a given import batch
     */
    public function post(Request $request, string $importRef)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userId = $request->input('user_id');

        $stagedTxns = nupay_transactions_staging::where('import_ref', $importRef)
                        ->whereNull('posted_at')
                        ->get();

        if ($stagedTxns->isEmpty()) {
            return back()->with('error', "No unposted transactions for import_ref {$importRef}");
        }

        $summary = [
            'total'   => $stagedTxns->count(),
            'success' => 0,
            'failed'  => [],
        ];

        foreach ($stagedTxns as $txn) {
            try {
                $this->nuPayService->postTransaction($txn->id, $userId);
                $summary['success']++;
            } catch (Exception $e) {
                Log::error("Failed to post NuPay txn #{$txn->id}: " . $e->getMessage());
                $summary['failed'][] = [
                    'txn_id' => $txn->id,
                    'error'  => $e->getMessage()
                ];
            }
        }

        return redirect()->route('nu-pay.import.index')->with('success', "Batch '{$importRef}' posted. Success: {$summary['success']}, Failed: " . count($summary['failed']));
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
