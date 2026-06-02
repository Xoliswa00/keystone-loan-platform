<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AccountDetailController;
use App\Http\Controllers\LoanInterestController;
use App\Http\Controllers\RepaymentScheduleController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\NupayTransactionsStagingController;
use App\Http\Controllers\NupayTransactionController;

// ──────────────────────────────────────────────────────────────────────────────
// Public routes
// ──────────────────────────────────────────────────────────────────────────────

Route::get('/', fn() => view('welcome'));
Route::view('/about',   'about')->name('about');
Route::view('/contact', 'contact')->name('contact');
Route::get('/terms', [LoanApplicationController::class, 'terms'])->name('terms');

// ──────────────────────────────────────────────────────────────────────────────
// Dashboard — redirect admins to admin panel
// ──────────────────────────────────────────────────────────────────────────────

Route::get('/dashboard', function () {
    $user = auth()->user();
    return $user->rule_id === 2
        ? redirect()->route('admin.dashboard')
        : view('dashboard');
})->middleware(['auth'])->name('dashboard');

// ──────────────────────────────────────────────────────────────────────────────
// Authenticated — all users
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    // ── User profile (Laravel Breeze) ────────────────────────────────────────
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Customer financial profile & documents ───────────────────────────────
    Route::prefix('my-profile')->name('customer-profile.')->group(function () {
        Route::get('/',           [CustomerProfileController::class, 'show'])->name('show');
        Route::post('/financial', [CustomerProfileController::class, 'saveAffordability'])->name('save-affordability');
        Route::post('/documents', [CustomerProfileController::class, 'uploadDocument'])->name('upload-document');
        Route::get('/pre-qual',   [CustomerProfileController::class, 'preQualification'])->name('pre-qual');
    });

    // ── Account details (bank accounts) ─────────────────────────────────────
    Route::resource('accountdetails', AccountDetailController::class);

    // ── Loan applications (client-facing) ───────────────────────────────────
    Route::resource('loanapplications', LoanApplicationController::class);
    Route::resource('applications', LoanApplicationController::class);
    Route::put('/loan-applications/{id}/note',
        [LoanApplicationController::class, 'updateNote'])->name('loanapplications.updateNote');

    // ── Loans (client-facing) ────────────────────────────────────────────────
    Route::resource('loan', LoanController::class);
    Route::post('loan/{loan}/update-payment', [LoanController::class, 'updatePayment'])
        ->name('loans.updatePayment');

    // ── Repayment schedules & repayments ────────────────────────────────────
    Route::resource('repaymentSchedules', RepaymentScheduleController::class);
    Route::resource('loanrepayments', LoanRepaymentController::class);

    // ── Transactions & interests ─────────────────────────────────────────────
    Route::resource('transactions',  TransactionController::class);
    Route::resource('loaninterests', LoanInterestController::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Admin — role_id === 2 required
// All admin routes sit behind a middleware check. Add a dedicated `admin`
// middleware class when ready; for now we use a closure middleware inline.
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware(['auth', function ($request, $next) {
    if (auth()->user()?->rule_id !== 2) {
        abort(403, 'Admin access required.');
    }
    return $next($request);
}])->group(function () {

    // ── Dashboards & overview ────────────────────────────────────────────────
    Route::get('/Admins',   [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/Admin',    [AdminController::class, 'Loans'])->name('admin.loans');
    Route::get('/Admind',   [AdminController::class, 'Disbursement'])->name('Admin.Disbursement');
    Route::get('/summary',  [AdminController::class, 'summary'])->name('admin.summary');

    // ── Application management ───────────────────────────────────────────────
    Route::get('/admin/applications', [LoanApplicationController::class, 'adminIndex'])
        ->name('admin.applications.index');

    // Approve / reject actions (POST to avoid accidental GET triggers)
    Route::post('admin/loans/{id}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('admin/loans/{id}/reject',  [LoanController::class, 'reject'])->name('loans.reject');
    Route::post('admin/loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');

    // ── Customers ────────────────────────────────────────────────────────────
    Route::resource('customers', CustomerController::class);

    // ── Disbursements ────────────────────────────────────────────────────────
    Route::prefix('admin/disbursements')->name('disbursements.')->group(function () {
        Route::get('/',               [DisbursementController::class, 'index'])->name('index');
        Route::post('/{id}/approve',  [DisbursementController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject',   [DisbursementController::class, 'reject'])->name('reject');
        Route::post('/{id}/release',  [DisbursementController::class, 'release'])->name('release');
        Route::post('/approve-all',   [DisbursementController::class, 'approveAll'])->name('approveAll');
    });

    // ── Document verification ─────────────────────────────────────────────────
    Route::post('admin/documents/{document}/verify',
        [CustomerProfileController::class, 'verifyDocument'])->name('admin.documents.verify');

    // ── Nu-Pay imports ────────────────────────────────────────────────────────
    Route::get('/nupay/upload',  [NupayTransactionsStagingController::class, 'showUploadForm'])->name('nupay.upload.form');
    Route::post('/nupay/upload', [NupayTransactionsStagingController::class, 'handleUpload'])->name('nupay.upload');

    Route::prefix('nu-pay/import')->name('nu-pay.import.')->group(function () {
        Route::get('/',               [NupayTransactionController::class, 'index'])->name('index');
        Route::get('/{importRef}',    [NupayTransactionController::class, 'show'])->name('show');
        Route::post('/{importRef}/post', [NupayTransactionController::class, 'post'])->name('post');
    });

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::prefix('admin/reports')->name('reports.')->group(function () {
        Route::get('/summary',          [AdminController::class, 'profitability'])->name('summary');
        Route::get('/profitability',    [AdminController::class, 'profitability'])->name('profitability');
        Route::get('/portfolio',        [AdminController::class, 'portfolio'])->name('portfolio');
        Route::get('/arrears',          [AdminController::class, 'arrears'])->name('arrears');
        Route::get('/collections',      [AdminController::class, 'collections'])->name('collections');
        Route::get('/disbursements',    [AdminController::class, 'disbursements'])->name('disbursements');
        Route::get('/gl-reconciliation',[AdminController::class, 'glReconciliation'])->name('gl-recon');
        Route::get('/gl-summary',       [AdminController::class, 'glReconciliation'])->name('gl_summary');
        Route::get('/reversals',        [AdminController::class, 'reversalAudit'])->name('reversals');
        Route::get('/scorecard',        [AdminController::class, 'scorecard'])->name('scorecard');
        Route::get('/reviewer/{id}',    [AdminController::class, 'reviewer'])->name('reviewer');
        Route::get('/alerts',           [AdminController::class, 'alerts'])->name('alerts');
    });

    // ── Admin resource (legacy) ───────────────────────────────────────────────
    Route::resource('Admin', AdminController::class);
});

// ──────────────────────────────────────────────────────────────────────────────
require __DIR__ . '/auth.php';
