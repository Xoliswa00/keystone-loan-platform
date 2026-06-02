<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\LoanApiController;
use App\Http\Controllers\Api\ProfileApiController;

/*
|--------------------------------------------------------------------------
| Keystone Capital Partners — Mobile API
|--------------------------------------------------------------------------
| All protected endpoints require:  Authorization: Bearer {token}
| Get token via: POST /api/auth/login
*/

// ── Public ───────────────────────────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:login');
    Route::get('/ping',   fn() => response()->json(['status'=>'ok','app'=>config('app.name')]));
});

// ── Authenticated (Sanctum token) ────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthApiController::class, 'logout']);
    Route::get('/auth/user',    [AuthApiController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [DashboardApiController::class, 'summary']);

    // Loans & applications
    Route::get('/products',    [LoanApiController::class, 'products']);
    Route::post('/calculate',  [LoanApiController::class, 'calculate']);
    Route::get('/loans',       [LoanApiController::class, 'index']);
    Route::get('/applications',[LoanApiController::class, 'applications']);
    Route::get('/schedule',    [LoanApiController::class, 'schedule']);
    Route::get('/payments',    [LoanApiController::class, 'payments']);

    // Profile
    Route::get('/profile',        [ProfileApiController::class, 'show']);
    Route::put('/profile/income', [ProfileApiController::class, 'updateIncome']);

    // Statement (async)
    Route::get('/statement/request', function (Request $request) {
        $user = $request->user();
        $period = now()->format('Y-m');
        $cached = \Illuminate\Support\Facades\Cache::get("statement_{$user->id}_{$period}");

        if ($cached && \Illuminate\Support\Facades\Storage::disk('public')->exists($cached)) {
            return response()->json(['ready' => true, 'download_url' => url('/api/statement/download')]);
        }

        $genKey = "statement_generating_{$user->id}_{$period}";
        if (!\Illuminate\Support\Facades\Cache::get($genKey)) {
            \Illuminate\Support\Facades\Cache::put($genKey, true, now()->addMinutes(5));
            \App\Jobs\GenerateClientStatement::dispatch($user->id, $period, false);
        }

        return response()->json(['ready' => false, 'message' => 'Generating — poll /api/statement/status in 15 seconds.'], 202);
    });

    Route::get('/statement/status', function (Request $request) {
        $user   = $request->user();
        $period = now()->format('Y-m');
        $path   = \Illuminate\Support\Facades\Cache::get("statement_{$user->id}_{$period}");
        $ready  = $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path);

        return response()->json([
            'ready'      => $ready,
            'generating' => (bool) \Illuminate\Support\Facades\Cache::get("statement_generating_{$user->id}_{$period}"),
            'download_url' => $ready ? url('/api/statement/download') : null,
        ]);
    });

    Route::get('/statement/download', function (Request $request) {
        $user   = $request->user();
        $period = now()->format('Y-m');
        $path   = \Illuminate\Support\Facades\Cache::get("statement_{$user->id}_{$period}");

        if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Statement not ready.'], 404);
        }

        $filename = 'KCP-Statement-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . $period . '.pdf';
        return response()->download(\Illuminate\Support\Facades\Storage::disk('public')->path($path), $filename, ['Content-Type' => 'application/pdf']);
    });

    // NCA Agreements
    Route::get('/agreements', function (Request $request) {
        return response()->json([
            'data' => \App\Models\NcaAgreement::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')->get()
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'reference'     => $a->reference,
                    'document_type' => $a->document_type,
                    'type_label'    => $a->getTypeLabel(),
                    'generated_at'  => $a->generated_at,
                    'accepted_at'   => $a->accepted_at,
                    'is_signed'     => $a->isSigned(),
                    'download_url'  => url("/api/agreements/{$a->id}/download"),
                ]),
        ]);
    });

    Route::get('/agreements/{agreement}/download', function (Request $request, \App\Models\NcaAgreement $agreement) {
        if ($agreement->user_id !== $request->user()->id) abort(403);
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($agreement->file_path)) {
            return response()->json(['message' => 'Document not found.'], 404);
        }
        return response()->download(
            \Illuminate\Support\Facades\Storage::disk('public')->path($agreement->file_path),
            $agreement->reference . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    });

    Route::post('/agreements/{agreement}/accept', function (Request $request, \App\Models\NcaAgreement $agreement) {
        if ($agreement->user_id !== $request->user()->id) abort(403);
        if ($agreement->isSigned()) return response()->json(['message' => 'Already signed.']);
        app(\App\Services\LoanAgreementService::class)->recordAcceptance($agreement, 'mobile_app');
        return response()->json(['message' => 'Agreement accepted.', 'accepted_at' => $agreement->fresh()->accepted_at]);
    });

    // ── Admin / Staff monitoring endpoints ────────────────────────────────────
    // Requires staff role token (obtained via same POST /api/auth/login but staff account)
    Route::prefix('admin')->middleware(function ($request, $next) {
        $user = $request->user();
        $role = $user?->system_role ?? ($user?->rule_id === 2 ? 'admin' : 'client');
        if (!in_array($role, ['admin', 'finance', 'it_admin', 'loan_officer'])) {
            return response()->json(['message' => 'Staff access required.'], 403);
        }
        return $next($request);
    })->group(function () {
        Route::get('/dashboard',                           [AdminApiController::class, 'dashboard']);
        Route::get('/disbursements',                       [AdminApiController::class, 'disbursements']);
        Route::post('/disbursements/{id}/approve',         [AdminApiController::class, 'approveDisbursement']);
        Route::post('/disbursements/{id}/reject',          [AdminApiController::class, 'rejectDisbursement']);
        Route::get('/overdue',                             [AdminApiController::class, 'overdue']);
        Route::get('/collections',                         [AdminApiController::class, 'collections']);
        Route::get('/alerts',                              [AdminApiController::class, 'alerts']);
        Route::get('/portfolio',                           [AdminApiController::class, 'portfolio']);
    });
});

});
