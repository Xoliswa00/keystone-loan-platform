<?php

namespace App\Http\Controllers;

use App\Models\nupay_transactions_staging;
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
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ]);

        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();

        try {
            $batch = $this->importService->importAndStage(
                $file->getPathname(),
                $originalName,
                Auth::id()
            );

            $warnings = $this->importService->warnings();
            $errors   = $this->importService->errors();

            $msg = "Import successful. Batch: {$batch->import_ref} — {$batch->row_count} rows staged.";

            if (!empty($warnings)) {
                $msg .= ' ' . count($warnings) . ' row(s) skipped (already imported).';
            }
            if (!empty($errors)) {
                $msg .= ' ' . count($errors) . ' row(s) had errors — check logs.';
            }

            return redirect()
                ->route('nu-pay.import.show', $batch->import_ref)
                ->with('success', $msg);

        } catch (\Throwable $e) {
            Log::error('NuPay upload failed', [
                'file'  => $originalName,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
