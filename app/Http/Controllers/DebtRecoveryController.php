<?php

namespace App\Http\Controllers;

use App\Models\DebtRecovery;
use App\Models\DebtRecoveryPayment;
use App\Models\Loan;
use App\Services\BadDebtProvisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebtRecoveryController extends Controller
{
    protected BadDebtProvisionService $provision;

    public function __construct(BadDebtProvisionService $provision)
    {
        $this->provision = $provision;
    }

    // ── List all active recovery cases ─────────────────────────────────────

    public function index(Request $request)
    {
        $query = DebtRecovery::with(['loan', 'user', 'assignedTo'])
            ->orderBy('next_action_date')
            ->orderByDesc('outstanding_recovery');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $cases   = $query->paginate(30);
        $summary = DebtRecovery::select(
            DB::raw('SUM(original_write_off_amount) as total_written_off'),
            DB::raw('SUM(total_recovered) as total_recovered'),
            DB::raw('SUM(outstanding_recovery) as total_outstanding'),
            DB::raw('COUNT(*) as total_cases'),
            DB::raw("SUM(CASE WHEN status='recovered' THEN 1 ELSE 0 END) as fully_recovered"),
            DB::raw("SUM(CASE WHEN status='legal' THEN 1 ELSE 0 END) as in_legal")
        )->first();

        return view('admin.recovery.index', compact('cases', 'summary'));
    }

    // ── Show single recovery case ──────────────────────────────────────────

    public function show(DebtRecovery $recovery)
    {
        $recovery->load(['loan', 'user', 'assignedTo', 'payments.receivedBy']);
        $notes = DB::table('admin_notes')
            ->where('loan_application_id', $recovery->loan->loan_application_id)
            ->whereIn('note_type', ['collections', 'legal'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.recovery.show', compact('recovery', 'notes'));
    }

    // ── Manually open a recovery case (e.g. for 90+ DPD not yet written off) ─

    public function open(Request $request)
    {
        $request->validate([
            'loan_id'    => 'required|exists:loans,id',
            'notes'      => 'nullable|string|max:1000',
            'assigned_to'=> 'nullable|exists:users,id',
        ]);

        $loan = Loan::findOrFail($request->loan_id);

        if (DebtRecovery::where('loan_id', $loan->id)->where('status', 'open')->exists()) {
            return back()->with('error', 'A recovery case is already open for this loan.');
        }

        $writeOffAmount = $loan->remaining_balance;

        DebtRecovery::create([
            'loan_id'                   => $loan->id,
            'user_id'                   => $loan->user_id,
            'status'                    => 'open',
            'original_write_off_amount' => $writeOffAmount,
            'total_recovered'           => 0,
            'outstanding_recovery'      => $writeOffAmount,
            'assigned_to'               => $request->assigned_to,
            'notes'                     => $request->notes,
            'opened_at'                 => now()->toDateString(),
            'next_action_date'          => now()->addDays(7)->toDateString(),
        ]);

        return redirect()->route('admin.recovery.index')
            ->with('success', 'Recovery case opened for loan #' . $loan->id);
    }

    // ── Record a recovery payment ──────────────────────────────────────────
    /**
     * When a written-off or NPL client makes a payment:
     * GL: Dr Bank → Cr Recovery Income (4400 — separate from normal interest income)
     * This keeps recovery income visible and separate on the P&L.
     */
    public function recordPayment(Request $request, DebtRecovery $recovery)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01|max:' . $recovery->outstanding_recovery,
            'payment_date'     => 'required|date',
            'payment_method'   => 'required|in:nupay,eft,cash,legal_settlement',
            'payment_reference'=> 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $recovery) {
            $amount = (float) $request->amount;

            // ── GL Posting ─────────────────────────────────────────────────
            $branchId     = $recovery->loan->branch_id    ?? 1;
            $locationCode = $recovery->loan->location_code ?? '000';

            $bankGl     = DB::table('glmappings')->where('key', 'loan_repayment_dr')->value('account_code');
            $recovIncGl = DB::table('glmappings')->where('key', 'recovery_income_cr')->value('account_code');

            $ref = 'ARB-REC-' . now()->format('YmdHis') . '-' . $recovery->id;

            // Create AR batch and GL entries for recovery payment
            $customer  = $recovery->loan->user->customer;
            $arBatch   = \App\Models\arbatch::create([
                'reference'    => $ref,
                'customer_id'  => $customer->id,
                'source_type'  => DebtRecovery::class,
                'source_id'    => $recovery->id,
                'total_amount' => $amount,
                'status'       => 'approved',
                'created_by'   => Auth::id(),
                'approved_by'  => Auth::id(),
                'approved_at'  => now(),
            ]);

            if ($bankGl && $recovIncGl) {
                $bankAccount = DB::table('gl_accounts as ga')
                    ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
                    ->where('coa.account_code', $bankGl)
                    ->where('ga.branch_id', $branchId)
                    ->value('ga.id');

                $recovAccount = DB::table('gl_accounts as ga')
                    ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
                    ->where('coa.account_code', $recovIncGl)
                    ->value('ga.id');

                if ($bankAccount && $recovAccount) {
                    \App\Models\arbatch_entries::insert([
                        ['arbatch_id' => $arBatch->id, 'gl_account_id' => $bankAccount,   'entry_type' => 'debit',  'amount' => $amount, 'description' => "Recovery payment #{$recovery->id}", 'created_at' => now(), 'updated_at' => now()],
                        ['arbatch_id' => $arBatch->id, 'gl_account_id' => $recovAccount,  'entry_type' => 'credit', 'amount' => $amount, 'description' => "Recovery income — loan #{$recovery->loan_id}", 'created_at' => now(), 'updated_at' => now()],
                    ]);

                    app(\App\Services\GLPostingService::class)->postArBatch($arBatch, Auth::id());
                    $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);
                }
            }

            // ── Record payment ─────────────────────────────────────────────
            DebtRecoveryPayment::create([
                'debt_recovery_id'  => $recovery->id,
                'loan_id'           => $recovery->loan_id,
                'user_id'           => $recovery->user_id,
                'amount'            => $amount,
                'payment_date'      => $request->payment_date,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'gl_batch_reference'=> $ref,
                'received_by'       => Auth::id(),
                'notes'             => $request->notes,
            ]);

            // ── Update recovery case ───────────────────────────────────────
            $newRecovered    = $recovery->total_recovered + $amount;
            $newOutstanding  = max(0, $recovery->original_write_off_amount - $newRecovered);

            $recovery->update([
                'total_recovered'      => $newRecovered,
                'outstanding_recovery' => $newOutstanding,
                'status'               => $newOutstanding <= 0 ? 'recovered' : 'partial',
            ]);

            \App\Models\AuditLog::record('recovery_payment', $recovery, [], [
                'amount' => $amount,
                'method' => $request->payment_method,
            ]);

            Log::info("Recovery payment R{$amount} recorded on case #{$recovery->id}");
        });

        return back()->with('success', 'Recovery payment of R' . number_format($request->amount, 2) . ' recorded.');
    }

    // ── Update case status / assign ────────────────────────────────────────

    public function update(Request $request, DebtRecovery $recovery)
    {
        $request->validate([
            'status'           => 'required|in:open,partial,recovered,closed,legal',
            'assigned_to'      => 'nullable|exists:users,id',
            'external_agency'  => 'nullable|string|max:200',
            'legal_reference'  => 'nullable|string|max:200',
            'next_action_date' => 'nullable|date',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $recovery->update($request->only([
            'status', 'assigned_to', 'external_agency',
            'legal_reference', 'next_action_date', 'notes',
        ]));

        if (in_array($request->status, ['recovered', 'closed'])) {
            $recovery->update(['closed_at' => now()->toDateString(), 'closed_by' => Auth::id()]);
        }

        return back()->with('success', 'Recovery case updated.');
    }
}
