<?php

namespace App\Http\Controllers;

use App\Models\import_batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Business Bank Statement Module
 *
 * Manages Keystone's OWN bank statements — not customer statements.
 * Performs 3-way reconciliation:
 *   1. Business Bank (what the bank records)
 *   2. Nu-Pay Batches (what Nu-Pay collected and remitted)
 *   3. GL / System (what the system recorded)
 *
 * Discrepancies between these three = items to investigate.
 */
class BusinessBankStatementController extends Controller
{
    public function index()
    {
        $batches = import_batch::where('source', 'business_bank')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.finance.business_bank_index', compact('batches'));
    }

    public function showUpload()
    {
        $recentBatches = import_batch::where('source', 'business_bank')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('admin.finance.business_bank_upload', compact('recentBatches'));
    }

    public function handleUpload(Request $request)
    {
        $request->validate([
            'file'          => 'required|file|mimes:csv,txt|max:20480',
            'bank_name'     => 'required|string|max:100',
            'account_name'  => 'required|string|max:100',
            'period'        => 'required|string|max:7',
            'account_number'=> 'nullable|string|max:20',
        ]);

        $file     = $request->file('file');
        $checksum = md5_file($file->getPathname());

        if (import_batch::where('checksum', $checksum)->exists()) {
            return back()->with('error', 'This statement has already been imported.');
        }

        $importRef  = 'BB-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        $storedPath = "business_bank/{$importRef}/" . $file->getClientOriginalName();
        Storage::disk('local')->put($storedPath, file_get_contents($file->getPathname()));

        $batch = import_batch::create([
            'source'            => 'business_bank',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $storedPath,
            'checksum'          => $checksum,
            'status'            => 'UPLOADED',
            'import_ref'        => $importRef,
            'processed_by'      => Auth::id(),
            'row_count'         => 0,
            'meta'              => json_encode([
                'bank_name'      => $request->bank_name,
                'account_name'   => $request->account_name,
                'account_number' => $request->account_number,
                'period'         => $request->period,
            ]),
        ]);

        $count = $this->parseBankCsv($file->getPathname(), $batch->id, $importRef);
        $batch->update(['row_count' => $count, 'status' => 'CAPTURED']);

        return redirect()->route('admin.finance.business-bank.show', $batch->id)
            ->with('success', "Business bank statement imported: {$count} transactions.");
    }

    public function show(import_batch $batch)
    {
        $lines = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->orderBy('transaction_date')
            ->paginate(100);

        $summary = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(credit_amount) as total_credits'),
                DB::raw('SUM(debit_amount) as total_debits'),
                DB::raw("SUM(CASE WHEN match_status='matched' THEN 1 ELSE 0 END) as matched"),
                DB::raw("SUM(CASE WHEN match_status='unmatched' THEN 1 ELSE 0 END) as unmatched"),
                DB::raw("SUM(CASE WHEN match_status='exception' THEN 1 ELSE 0 END) as exceptions")
            )
            ->first();

        $meta   = json_decode($batch->meta ?? '{}', true);
        $period = $meta['period'] ?? now()->format('Y-m');

        // 3-way reconciliation summary
        $reconciliation = $this->buildReconciliation($batch, $period);

        return view('admin.finance.business_bank_show', compact('batch', 'lines', 'summary', 'meta', 'reconciliation'));
    }

    /**
     * Auto-match business bank credits to Nu-Pay remittances,
     * and debits to loan disbursements and facility repayments.
     */
    public function reconcile(import_batch $batch)
    {
        $lines    = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->where('match_status', 'unmatched')
            ->get();

        $matched   = 0;
        $exception = 0;

        foreach ($lines as $line) {
            $matched_entity = null;

            if ($line->credit_amount > 0) {
                // Try to match credit to a Nu-Pay batch (Nu-Pay remits collected amounts)
                $nupayBatch = DB::table('import_batches as ib')
                    ->join('nupay_transactions_stagings as nts', 'nts.import_ref', '=', 'ib.import_ref')
                    ->where('ib.source', 'nupay')
                    ->whereDate('nts.action_date', Carbon::parse($line->transaction_date)->toDateString())
                    ->select('ib.id', DB::raw('SUM(nts.instalment_amount) as nupay_total'))
                    ->groupBy('ib.id')
                    ->havingRaw('ABS(SUM(nts.instalment_amount) - ?) < 50', [$line->credit_amount]) // within R50
                    ->first();

                if ($nupayBatch) {
                    DB::table('bank_statement_lines')->where('id', $line->id)->update([
                        'match_status' => 'matched',
                        'match_note'   => "Nu-Pay batch #{$nupayBatch->id} — total R{$nupayBatch->nupay_total} vs bank R{$line->credit_amount}",
                    ]);
                    $matched++;
                    continue;
                }

                // Try to match to a facility drawdown
                $facilityDraw = DB::table('funding_facility_transactions as fft')
                    ->where('fft.transaction_type', 'drawdown')
                    ->whereBetween('fft.transaction_date', [
                        Carbon::parse($line->transaction_date)->subDays(2)->toDateString(),
                        Carbon::parse($line->transaction_date)->addDays(2)->toDateString(),
                    ])
                    ->whereRaw('ABS(fft.amount - ?) < 10', [$line->credit_amount])
                    ->first();

                if ($facilityDraw) {
                    DB::table('bank_statement_lines')->where('id', $line->id)->update([
                        'match_status' => 'matched',
                        'match_note'   => "Facility drawdown — R{$facilityDraw->amount} on {$facilityDraw->transaction_date}",
                    ]);
                    $matched++;
                    continue;
                }
            }

            if ($line->debit_amount > 0) {
                // Try to match debit to a loan disbursement
                $disbursement = DB::table('loan_disbursements as ld')
                    ->whereBetween('ld.disbursement_date', [
                        Carbon::parse($line->transaction_date)->subDays(2)->toDateString(),
                        Carbon::parse($line->transaction_date)->addDays(2)->toDateString(),
                    ])
                    ->whereRaw('ABS(ld.disbursed_amount - ?) < 10', [$line->debit_amount])
                    ->where('ld.status', 'released')
                    ->first();

                if ($disbursement) {
                    DB::table('bank_statement_lines')->where('id', $line->id)->update([
                        'match_status' => 'matched',
                        'match_note'   => "Loan disbursement #{$disbursement->id} — R{$disbursement->disbursed_amount}",
                    ]);
                    $matched++;
                    continue;
                }

                // Try to match to a facility repayment
                $facilityRep = DB::table('funding_facility_transactions')
                    ->whereIn('transaction_type', ['repayment', 'interest_payment'])
                    ->whereBetween('transaction_date', [
                        Carbon::parse($line->transaction_date)->subDays(2)->toDateString(),
                        Carbon::parse($line->transaction_date)->addDays(2)->toDateString(),
                    ])
                    ->whereRaw('ABS(amount - ?) < 10', [$line->debit_amount])
                    ->first();

                if ($facilityRep) {
                    DB::table('bank_statement_lines')->where('id', $line->id)->update([
                        'match_status' => 'matched',
                        'match_note'   => "Facility " . str_replace('_',' ',$facilityRep->transaction_type) . " — R{$facilityRep->amount}",
                    ]);
                    $matched++;
                    continue;
                }
            }

            // Mark as exception
            DB::table('bank_statement_lines')->where('id', $line->id)->update([
                'match_status' => 'exception',
                'match_note'   => "No match found for " . ($line->credit_amount > 0 ? "credit R{$line->credit_amount}" : "debit R{$line->debit_amount}"),
            ]);
            $exception++;
        }

        $batch->update(['status' => $exception === 0 ? 'VALIDATED' : 'CAPTURED']);

        return back()->with('success', "Reconciliation: {$matched} matched, {$exception} exceptions.");
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function buildReconciliation(import_batch $batch, string $period): array
    {
        try {
            [$year, $month] = explode('-', $period);

            // Bank side
            $bankCredits = DB::table('bank_statement_lines')
                ->where('import_batch_id', $batch->id)->sum('credit_amount');
            $bankDebits  = DB::table('bank_statement_lines')
                ->where('import_batch_id', $batch->id)->sum('debit_amount');

            // Nu-Pay side — total collected in this period
            $nupayTotal = DB::table('nupay_transactions_stagings')
                ->whereYear('action_date', $year)
                ->whereMonth('action_date', $month)
                ->where('transaction_type', 'success')
                ->sum('instalment_amount');

            // System side — total disbursements in this period
            $disbTotal = DB::table('loan_disbursements')
                ->where('status', 'released')
                ->whereYear('disbursement_date', $year)
                ->whereMonth('disbursement_date', $month)
                ->sum('disbursed_amount');

            return [
                'bank_credits'  => $bankCredits,
                'bank_debits'   => $bankDebits,
                'nupay_total'   => $nupayTotal,
                'disb_total'    => $disbTotal,
                'collections_variance' => $bankCredits - $nupayTotal,
                'disbursement_variance'=> $bankDebits  - $disbTotal,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function parseBankCsv(string $filePath, int $batchId, string $importRef): int
    {
        $handle  = fopen($filePath, 'r');
        $headers = null;
        $rows    = [];
        $count   = 0;

        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (empty(array_filter($line))) continue;

            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $line);
                continue;
            }

            $row = array_combine($headers, array_pad(array_slice($line, 0, count($headers)), count($headers), null));

            $date    = $this->findVal($row, ['date','transaction date','value date']);
            $desc    = $this->findVal($row, ['description','narrative','details']);
            $ref     = $this->findVal($row, ['reference','ref','cheque no']);
            $debit   = (float) preg_replace('/[^0-9.-]/', '', $this->findVal($row, ['debit','debit amount','payment']) ?? '0');
            $credit  = (float) preg_replace('/[^0-9.-]/', '', $this->findVal($row, ['credit','credit amount','deposit','receipts']) ?? '0');
            $balance = (float) preg_replace('/[^0-9.-]/', '', $this->findVal($row, ['balance','running balance']) ?? '0');

            if (!$date || !$desc) continue;

            try {
                $parsedDate = Carbon::parse($date)->toDateString();
            } catch (\Exception $e) { continue; }

            $rows[] = [
                'import_batch_id'  => $batchId,
                'import_ref'       => $importRef,
                'transaction_date' => $parsedDate,
                'description'      => substr($desc ?? '', 0, 500),
                'reference'        => substr($ref ?? '', 0, 100),
                'debit_amount'     => $debit,
                'credit_amount'    => $credit,
                'running_balance'  => $balance ?: null,
                'match_status'     => 'unmatched',
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
            $count++;
        }

        fclose($handle);

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('bank_statement_lines')->insert($chunk);
        }

        return $count;
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
