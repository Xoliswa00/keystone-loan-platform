<?php

namespace App\Http\Controllers;

use App\Models\import_batch;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles allocation of bank statement lines to GL accounts.
 * Works for both business bank statements and customer bank statements.
 *
 * The goal: every single bank line must have a GL home.
 * When all lines are matched AND GL bank balance = statement closing balance,
 * the reconciliation is complete and the period can be closed.
 */
class BankAllocationController extends Controller
{
    protected BankReconciliationService $recon;

    public function __construct(BankReconciliationService $recon)
    {
        $this->recon = $recon;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show the full reconciliation workspace for a batch
    // ──────────────────────────────────────────────────────────────────────────

    public function workspace(import_batch $batch)
    {
        $meta = json_decode($batch->meta ?? '{}', true);
        $source = $batch->source;

        // All lines grouped by status
        $unmatched = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->whereIn('match_status', ['unmatched', 'exception'])
            ->orderBy('transaction_date')
            ->get();

        $matched = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->where('match_status', 'matched')
            ->orderBy('transaction_date')
            ->get();

        // Recon status (pass statement closing balance if we have it)
        $closingBalance = $meta['closing_balance'] ?? null;
        $openingBalance = $meta['opening_balance'] ?? null;
        $status = $this->recon->getReconciliationStatus($batch->id, $openingBalance ? (float) $openingBalance : null, $closingBalance ? (float) $closingBalance : null);

        // GL expense accounts for the dropdown (5xxx + inter-company)
        $expenseAccounts = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
            ->whereIn('coa.account_category', ['expense', 'liability', 'asset'])
            ->select('ga.id', 'coa.account_code', 'coa.account_category', 'coa.account_type')
            ->orderBy('coa.account_code')
            ->get();

        // Common expense categories for quick-pick
        $quickPicks = $this->getQuickPicks();

        return view('admin.finance.bank_reconciliation', compact(
            'batch', 'meta', 'source', 'unmatched', 'matched',
            'status', 'expenseAccounts', 'quickPicks',
            'openingBalance', 'closingBalance'
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Auto-match all unmatched lines
    // ──────────────────────────────────────────────────────────────────────────

    public function autoMatch(import_batch $batch)
    {
        $results = $this->recon->autoMatch($batch->id, $batch->source);

        return redirect()->route('admin.finance.recon.workspace', $batch->id)
            ->with('success', "Auto-match: {$results['matched']} lines matched. {$results['remaining']} still need manual allocation.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Allocate a single line
    // ──────────────────────────────────────────────────────────────────────────

    public function allocateLine(Request $request, import_batch $batch)
    {
        $request->validate([
            'line_id' => 'required|integer',
            'match_category' => 'required|in:nu_pay_collection,loan_disbursement,facility_drawdown,facility_repayment,expense,recovery_payment,inter_account,other',
            'gl_account_id' => 'required_if:match_category,expense,inter_account,other|nullable|integer',
            'description' => 'required|string|max:300',
        ]);

        try {
            $this->recon->allocateLine(
                (int) $request->line_id,
                $request->match_category,
                (int) ($request->gl_account_id ?? $this->defaultGlForCategory($request->match_category)),
                $request->description,
                Auth::id()
            );

            return redirect()->route('admin.finance.recon.workspace', $batch->id)
                ->with('success', 'Line allocated and posted to GL.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Allocation failed: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk allocate — same GL account for selected lines
    // ──────────────────────────────────────────────────────────────────────────

    public function bulkAllocate(Request $request, import_batch $batch)
    {
        $request->validate([
            'line_ids' => 'required|array|min:1',
            'line_ids.*' => 'integer',
            'match_category' => 'required|in:expense,inter_account,other,nu_pay_collection,loan_disbursement,facility_drawdown,facility_repayment',
            'gl_account_id' => 'nullable|integer',
            'description' => 'required|string|max:300',
        ]);

        $results = $this->recon->bulkAllocate(
            $request->line_ids,
            $request->match_category,
            (int) ($request->gl_account_id ?? $this->defaultGlForCategory($request->match_category)),
            $request->description,
            Auth::id()
        );

        $msg = "{$results['allocated']} line(s) allocated.";
        if (! empty($results['errors'])) {
            $msg .= ' Errors: '.implode('; ', $results['errors']);
        }

        return redirect()->route('admin.finance.recon.workspace', $batch->id)
            ->with('success', $msg);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Undo a mistaken allocation — reverses any posted GL entry and returns
    // the line to unmatched.
    // ──────────────────────────────────────────────────────────────────────────

    public function unallocateLine(Request $request, import_batch $batch)
    {
        $request->validate(['line_id' => 'required|integer']);

        try {
            $this->recon->unallocateLine((int) $request->line_id, Auth::id());

            return redirect()->route('admin.finance.recon.workspace', $batch->id)
                ->with('success', 'Line unallocated. It is now unmatched again.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Unallocate failed: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Update statement opening/closing balance (for final balance check)
    // ──────────────────────────────────────────────────────────────────────────

    public function updateBalances(Request $request, import_batch $batch)
    {
        $request->validate([
            'opening_balance' => 'required|numeric',
            'closing_balance' => 'required|numeric',
        ]);

        $meta = json_decode($batch->meta ?? '{}', true);
        $meta['opening_balance'] = $request->opening_balance;
        $meta['closing_balance'] = $request->closing_balance;

        $batch->update(['meta' => json_encode($meta)]);

        return redirect()->route('admin.finance.recon.workspace', $batch->id)
            ->with('success', 'Statement balances saved.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mark reconciliation complete (lock the period)
    // ──────────────────────────────────────────────────────────────────────────

    public function markComplete(import_batch $batch)
    {
        $meta = json_decode($batch->meta ?? '{}', true);
        $status = $this->recon->getReconciliationStatus(
            $batch->id,
            isset($meta['opening_balance']) ? (float) $meta['opening_balance'] : null,
            isset($meta['closing_balance']) ? (float) $meta['closing_balance'] : null
        );

        if (! $status['all_lines_matched']) {
            return redirect()->back()
                ->with('error', "Cannot complete: {$status['unmatched_lines']} unmatched line(s) remain. Allocate all items first.");
        }

        if ($status['balance_reconciled'] === false) {
            return redirect()->back()
                ->with('error', 'Cannot complete: GL bank balance (R'.number_format($status['gl_bank_balance'], 2).') does not match statement closing balance (R'.number_format($status['statement_closing'], 2).'). Investigate the variance.');
        }

        $batch->update(['status' => 'RECONCILED']);

        return redirect()->route('admin.finance.recon.workspace', $batch->id)
            ->with('success', 'Reconciliation complete. Period is locked.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function defaultGlForCategory(string $category): int
    {
        // Return the gl_accounts.id for obvious categories
        $mapping = [
            'nu_pay_collection' => 'loan_repayment_dr',   // bank account
            'loan_disbursement' => 'loan_repayment_dr',
            'facility_drawdown' => 'loan_repayment_dr',
            'facility_repayment' => 'loan_repayment_dr',
        ];

        if (isset($mapping[$category])) {
            $glMapping = DB::table('glmappings')->where('key', $mapping[$category])->first();
            if ($glMapping) {
                $gl = DB::table('gl_accounts as ga')
                    ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
                    ->where('coa.account_code', $glMapping->account_code)
                    ->value('ga.id');

                return $gl ?? 1;
            }
        }

        return 1; // fallback
    }

    protected function getQuickPicks(): array
    {
        // Common expense types for a lending business — map to COA codes
        return [
            ['label' => 'Bank Charges',        'code' => '5200', 'category' => 'expense'],
            ['label' => 'Nu-Pay Collection Fee', 'code' => '5200', 'category' => 'expense'],
            ['label' => 'Staff Salaries',       'code' => '5300', 'category' => 'expense'],
            ['label' => 'NCR Levies',           'code' => '5400', 'category' => 'expense'],
            ['label' => 'Facility Interest',    'code' => '5600', 'category' => 'expense'],
            ['label' => 'Facility Fee',         'code' => '5610', 'category' => 'expense'],
            ['label' => 'Operating Expenses',   'code' => '5500', 'category' => 'expense'],
        ];
    }
}
