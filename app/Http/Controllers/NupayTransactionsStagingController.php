<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storenupay_transactions_stagingRequest;
use App\Http\Requests\Updatenupay_transactions_stagingRequest;
use App\Models\nupay_transactions_staging;
use App\Models\import_batch;
use App\Services\NuPayService;
use Exception;
use App\Models\nupay_transactions;
use Illuminate\Http\Request;
use App\Services\NupayImportService;
use Illuminate\Support\Facades\Log;


class NupayTransactionsStagingController extends Controller
{
     protected NupayImportService $importService;

    public function __construct(NupayImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Show NuPay file upload form
     */
    public function showUploadForm()
    {
        return view('admin.imports.nupay_upload');
    }

    /**
     * Handle file upload and import into staging table
     */
    public function handleUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $file = $request->file('file');

            $cleanedRows = $this->importService->import(
                $file->getPathname()
            );

            return redirect()
                ->back()
                ->with('success', 'File uploaded successfully. Rows processed: ' . $cleanedRows->count());

        } catch (\Throwable $e) {
            Log::error('NuPay import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'File import failed. Please check logs.');
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Storenupay_transactions_stagingRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(nupay_transactions_staging $nupay_transactions_staging)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Updatenupay_transactions_stagingRequest $request, nupay_transactions_staging $nupay_transactions_staging)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(nupay_transactions_staging $nupay_transactions_staging)
    {
        //
    }
}
