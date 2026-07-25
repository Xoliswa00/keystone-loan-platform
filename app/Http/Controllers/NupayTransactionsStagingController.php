<?php

namespace App\Http\Controllers;

use App\Models\import_batch;
use App\Services\NupayImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NupayTransactionsStagingController extends Controller
{
    protected NupayImportService $importService;

    public function __construct(NupayImportService $importService)
    {
        $this->importService = $importService;
    }

    public function showUploadForm()
    {
        $recentBatches = import_batch::where('source', 'nupay')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('admin.Imports.nupay_upload', compact('recentBatches'));
    }

    /**
     * Handle CSV or Excel upload — replaces the Python → SQL INSERT manual process.
     * Accepts: .csv, .xlsx, .xls
     * Creates import_batch + stages all rows into nupay_transactions_stagings.
     */
    public function handleUpload(Request $request)
    {
        $request->validate([
            // See BusinessBankStatementController::handleUpload() — `mimes`
            // content-sniffs and rejects legitimate CSV/Excel exports (BOM,
            // odd encodings); `extensions` trusts the file's extension.
            'file' => 'required|file|extensions:csv,txt,xlsx,xls|max:20480',
            // Only required for CSV/TXT — a flat file has no tab to infer the
            // type from. Excel uploads self-identify per sheet and ignore this.
            'transaction_type' => 'nullable|in:success,failed,tracking,reversed',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['csv', 'txt'], true) && ! $request->filled('transaction_type')) {
            return redirect()->back()
                ->with('error', 'Please select a transaction type for CSV/TXT uploads.');
        }

        try {
            $batch = $this->importService->importAndStage(
                $file->getPathname(),
                $originalName,
                Auth::id(),
                $request->input('transaction_type')
            );

            $warnings = $this->importService->warnings();
            $errors = $this->importService->errors();

            $msg = "Import successful. Batch: {$batch->import_ref} — {$batch->row_count} rows staged.";

            if (! empty($warnings)) {
                $msg .= ' '.count($warnings).' row(s) skipped (already imported).';
            }
            if (! empty($errors)) {
                // Show the actual reasons, not just a count — "check logs"
                // means opening a file most admin users can't reach; the
                // first few rows' errors already say exactly what's wrong
                // (which mandate_id, which column).
                $msg .= ' '.count($errors).' row(s) had errors: '.implode(' | ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $msg .= ' (+'.(count($errors) - 5).' more — see logs)';
                }
            }

            return redirect()
                ->route('nu-pay.import.show', $batch->import_ref)
                ->with('success', $msg);

        } catch (\Throwable $e) {
            Log::error('NuPay upload failed', [
                'file' => $originalName,
                'uploaded_by' => Auth::id(),
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
