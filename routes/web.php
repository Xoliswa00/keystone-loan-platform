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
use App\Http\Controllers\LoanAgreementController;

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

    // ── NCA Agreements (accessible to both client and admin) ─────────────────
    Route::prefix('agreements')->name('agreements.')->group(function () {
        Route::get('/application/{application}',  [LoanAgreementController::class, 'index'])->name('index');
        Route::get('/download/{agreement}',       [LoanAgreementController::class, 'download'])->name('download');
        Route::post('/accept/{agreement}',        [LoanAgreementController::class, 'accept'])->name('accept');
    });

    // ── Transactions & interests ─────────────────────────────────────────────
    Route::resource('transactions',  TransactionController::class);
    Route::resource('loaninterests', LoanInterestController::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Admin — role_id === 2 required
// All admin routes sit behind a middleware check. Add a dedicated `admin`
// middleware class when ready; for now we use a closure middleware inline.
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'role:admin,loan_officer,finance,it_admin'])->group(function () {

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

    // ── NCA Agreement generation (admin only) ─────────────────────────────────
    Route::post('/admin/agreements/{application}/pre-agreement',
        [LoanAgreementController::class, 'generatePreAgreement'])->name('admin.agreements.pre-agreement');
    Route::post('/admin/agreements/{application}/loan-agreement',
        [LoanAgreementController::class, 'generateLoanAgreement'])->name('admin.agreements.loan-agreement');
    Route::post('/admin/agreements/{loan}/settlement-quote',
        [LoanAgreementController::class, 'generateSettlementQuote'])->name('admin.agreements.settlement-quote');
    Route::get('/admin/agreements/{application}/preview/{type?}',
        [LoanAgreementController::class, 'preview'])->name('admin.agreements.preview');

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
        Route::get('/reversals',           [AdminController::class, 'reversalAudit'])->name('reversals');
        Route::get('/scorecard',           [AdminController::class, 'scorecard'])->name('scorecard');
        Route::get('/reviewer/{id}',       [AdminController::class, 'reviewer'])->name('reviewer');
        Route::get('/alerts',              [AdminController::class, 'alerts'])->name('alerts');

        // ── Finance-specific ──────────────────────────────────────────────────
        Route::get('/vat-201',             [AdminController::class, 'vat201'])->name('vat201');
        Route::get('/income-statement',    [AdminController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/balance-sheet',       [AdminController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/write-off-register',  [AdminController::class, 'writeOffRegister'])->name('write-off-register');
        Route::get('/audit-log',           [AdminController::class, 'auditLog'])->name('audit-log');
        Route::get('/npl',                 [AdminController::class, 'nplReport'])->name('npl');
    });

    // ── Bank statement import ─────────────────────────────────────────────────
    Route::get('/bank-statement/upload',   [\App\Http\Controllers\BankStatementController::class, 'showUpload'])->name('bank-statement.upload');
    Route::post('/bank-statement/upload',  [\App\Http\Controllers\BankStatementController::class, 'handleUpload'])->name('bank-statement.store');
    Route::get('/bank-statement/{batch}',  [\App\Http\Controllers\BankStatementController::class, 'show'])->name('bank-statement.show');
    Route::post('/bank-statement/{batch}/reconcile', [\App\Http\Controllers\BankStatementController::class, 'reconcile'])->name('bank-statement.reconcile');

    // ── Client statement PDF ──────────────────────────────────────────────────
    Route::get('/client/statement/{user}', [\App\Http\Controllers\ClientStatementController::class, 'download'])->name('client.statement');
    Route::get('/my-statement',            [\App\Http\Controllers\ClientStatementController::class, 'myStatement'])->name('client.my-statement');

    // ── Admin operations: notes + document verification ───────────────────────
    Route::post('/admin/notes/{application}',         [\App\Http\Controllers\AdminOpsController::class, 'addNote'])->name('admin.notes.store');
    Route::post('/admin/assign/{application}',        [\App\Http\Controllers\AdminOpsController::class, 'assign'])->name('admin.assign');
    Route::post('/admin/collections/log',             [\App\Http\Controllers\AdminOpsController::class, 'logContactAttempt'])->name('admin.collections.log');
    Route::get('/admin/collections',                  [\App\Http\Controllers\AdminOpsController::class, 'collectionsQueue'])->name('admin.collections');

    // ── NCR export (download) ─────────────────────────────────────────────────
    Route::get('/admin/ncr-export', [\App\Http\Controllers\AdminOpsController::class, 'ncrExportDownload'])->name('admin.ncr-export');

    // ── Debt recovery ─────────────────────────────────────────────────────────
    Route::prefix('admin/recovery')->name('admin.recovery.')->group(function () {
        Route::get('/',                  [\App\Http\Controllers\DebtRecoveryController::class, 'index'])->name('index');
        Route::get('/{recovery}',        [\App\Http\Controllers\DebtRecoveryController::class, 'show'])->name('show');
        Route::post('/open',             [\App\Http\Controllers\DebtRecoveryController::class, 'open'])->name('open');
        Route::post('/{recovery}/payment',[\App\Http\Controllers\DebtRecoveryController::class, 'recordPayment'])->name('payment');
        Route::patch('/{recovery}',      [\App\Http\Controllers\DebtRecoveryController::class, 'update'])->name('update');
    });

    // ── Admin resource (legacy) ───────────────────────────────────────────────
    Route::resource('Admin', AdminController::class);
});

// ──────────────────────────────────────────────────────────────────────────────
require __DIR__ . '/auth.php';
