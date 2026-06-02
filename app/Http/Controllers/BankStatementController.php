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

class BankStatementController extends Controller
{
    public function showUpload()
    {
        $batches = import_batch::where('source', 'bank_statement')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('admin.Imports.bank_statement_upload', compact('batches'));
    }

    /**
     * Upload a bank statement CSV.
     * Standard SA bank CSV format: Date, Description, Reference, Debit, Credit, Balance
     */
    public function handleUpload(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:csv,txt|max:10240',
            'bank_name'=> 'required|string|max:100',
            'period'   => 'required|string|max:7', // YYYY-MM
        ]);

        $file    = $request->file('file');
        $checksum = md5_file($file->getPathname());

        if (import_batch::where('checksum', $checksum)->exists()) {
            return back()->with('error', 'This bank statement file has already been imported.');
        }

        $importRef  = 'BS-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        $storedPath = "bank_statements/{$importRef}/" . $file->getClientOriginalName();
        Storage::disk('local')->put($storedPath, file_get_contents($file->getPathname()));

        $batch = import_batch::create([
            'source'            => 'bank_statement',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $storedPath,
            'checksum'          => $checksum,
            'status'            => 'UPLOADED',
            'import_ref'        => $importRef,
            'processed_by'      => Auth::id(),
            'row_count'         => 0,
            'meta'              => json_encode([
                'bank_name' => $request->bank_name,
                'period'    => $request->period,
            ]),
        ]);

        // Parse and stage
        $count = $this->parseBankCsv($file->getPathname(), $batch->id, $importRef);
        $batch->update(['row_count' => $count, 'status' => 'CAPTURED']);

        return redirect()->route('bank-statement.show', $batch->id)
            ->with('success', "Bank statement imported: {$count} transactions staged.");
    }

    public function show(import_batch $batch)
    {
        $lines = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->orderBy('transaction_date')
            ->paginate(50);

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

        return view('admin.Imports.bank_statement_show', compact('batch', 'lines', 'summary'));
    }

    /**
     * Auto-match bank statement credits to Nu-Pay staged transactions.
     * 3-way match: Bank → Nu-Pay → GL
     */
    public function reconcile(import_batch $batch)
    {
        $lines = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batch->id)
            ->where('match_status', 'unmatched')
            ->where('credit_amount', '>', 0)
            ->get();

        $matched   = 0;
        $exception = 0;

        foreach ($lines as $line) {
            // Try to match by amount + date (within 2 days)
            $nupayMatch = DB::table('nupay_transactions_stagings')
                ->where('instalment_amount', $line->credit_amount)
                ->whereBetween('action_date', [
                    Carbon::parse($line->transaction_date)->subDays(2)->toDateString(),
                    Carbon::parse($line->transaction_date)->addDays(2)->toDateString(),
                ])
                ->whereNotNull('posted_at')
                ->first();

            if ($nupayMatch) {
                DB::table('bank_statement_lines')
                    ->where('id', $line->id)
                    ->update([
                        'match_status'           => 'matched',
                        'matched_nupay_staging_id' => $nupayMatch->id,
                        'match_note'             => "Auto-matched: amount R{$line->credit_amount} on {$nupayMatch->action_date}",
                    ]);
                $matched++;
            } else {
                DB::table('bank_statement_lines')
                    ->where('id', $line->id)
                    ->update([
                        'match_status' => 'exception',
                        'match_note'   => "No Nu-Pay match found for R{$line->credit_amount} on {$line->transaction_date}",
                    ]);
                $exception++;
            }
        }

        $batch->update(['status' => 'VALIDATED']);

        return back()->with('success', "Reconciliation: {$matched} matched, {$exception} exceptions requiring review.");
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function parseBankCsv(string $filePath, int $batchId, string $importRef): int
    {
        $handle  = fopen($filePath, 'r');
        $headers = null;
        $count   = 0;
        $rows    = [];

        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (empty(array_filter($line))) continue;

            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $line);
                continue;
            }

            $row = array_combine($headers, array_pad($line, count($headers), null));

            // Flexible column name matching for different SA bank formats
            $date    = $this->findCol($row, ['date', 'transaction date', 'value date']);
            $desc    = $this->findCol($row, ['description', 'narrative', 'details', 'reference']);
            $ref     = $this->findCol($row, ['reference', 'ref', 'cheque no']);
            $debit   = (float) preg_replace('/[^0-9.-]/', '', $this->findCol($row, ['debit', 'debit amount', 'payment']) ?? '0');
            $credit  = (float) preg_replace('/[^0-9.-]/', '', $this->findCol($row, ['credit', 'credit amount', 'deposit', 'receipts']) ?? '0');
            $balance = (float) preg_replace('/[^0-9.-]/', '', $this->findCol($row, ['balance', 'running balance', 'closing balance']) ?? '0');

            if (!$date) continue;

            $rows[] = [
                'import_batch_id'  => $batchId,
                'import_ref'       => $importRef,
                'transaction_date' => Carbon::parse($date)->toDateString(),
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

        if (!empty($rows)) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('bank_statement_lines')->insert($chunk);
            }
        }

        return $count;
    }

    protected function findCol(array $row, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (isset($row[$c]) && $row[$c] !== null && $row[$c] !== '') {
                return $row[$c];
            }
        }
        return null;
    }
}
