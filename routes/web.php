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

    // ── Statement download (async) ────────────────────────────────────────────
    Route::get('/my-statement',                              [\App\Http\Controllers\ClientStatementController::class, 'myStatement'])->name('client.my-statement');
    Route::get('/my-statement/status',                       [\App\Http\Controllers\ClientStatementController::class, 'checkStatus'])->name('client.statement.status');
    Route::get('/statements/{user}/{period}',                [\App\Http\Controllers\ClientStatementController::class, 'serveFile'])->name('client.statement.file');
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
    Route::post('customers/{customer}/restrict',  [CustomerController::class, 'restrict'])->name('customers.restrict');
    Route::post('customers/{customer}/lift',      [CustomerController::class, 'lift'])->name('customers.lift');
    Route::post('customers/{customer}/blacklist', [CustomerController::class, 'toggleBlacklist'])->name('customers.blacklist');

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

    // ── Funding Facilities ────────────────────────────────────────────────────
    Route::prefix('admin/funding')->name('admin.funding.')->group(function () {
        Route::get('/',                           [\App\Http\Controllers\FundingFacilityController::class, 'index'])->name('index');
        Route::get('/create',                     [\App\Http\Controllers\FundingFacilityController::class, 'create'])->name('create');
        Route::post('/',                          [\App\Http\Controllers\FundingFacilityController::class, 'store'])->name('store');
        Route::get('/{fundingFacility}',          [\App\Http\Controllers\FundingFacilityController::class, 'show'])->name('show');
        Route::get('/{fundingFacility}/edit',     [\App\Http\Controllers\FundingFacilityController::class, 'edit'])->name('edit');
        Route::put('/{fundingFacility}',          [\App\Http\Controllers\FundingFacilityController::class, 'update'])->name('update');
        Route::post('/{fundingFacility}/transaction', [\App\Http\Controllers\FundingFacilityController::class, 'recordTransaction'])->name('transaction');
        Route::post('/accrue-all',                [\App\Http\Controllers\FundingFacilityController::class, 'accrueAll'])->name('accrue-all');
        Route::post('/{fundingFacility}/upload-agreement', [\App\Http\Controllers\FundingFacilityController::class, 'uploadAgreement'])->name('upload-agreement');
    });

    // ── Business Bank Statement ───────────────────────────────────────────────
    Route::prefix('admin/finance')->name('admin.finance.')->group(function () {
        Route::get('/business-bank',              [\App\Http\Controllers\BusinessBankStatementController::class, 'showUpload'])->name('business-bank.upload');
        Route::post('/business-bank',             [\App\Http\Controllers\BusinessBankStatementController::class, 'handleUpload'])->name('business-bank.store');
        Route::get('/business-bank/{batch}',      [\App\Http\Controllers\BusinessBankStatementController::class, 'show'])->name('business-bank.show');
        Route::post('/business-bank/{batch}/reconcile', [\App\Http\Controllers\BusinessBankStatementController::class, 'reconcile'])->name('business-bank.reconcile');
    });

    // ── System Logs ───────────────────────────────────────────────────────────
    Route::prefix('admin/system')->name('admin.system.')->group(function () {
        Route::get('/logs',               [\App\Http\Controllers\SystemLogController::class, 'index'])->name('logs');
        Route::post('/logs/retry',        [\App\Http\Controllers\SystemLogController::class, 'retryJob'])->name('logs.retry');
        Route::post('/logs/clear-failed', [\App\Http\Controllers\SystemLogController::class, 'clearFailed'])->name('logs.clear-failed');
        Route::get('/logs/download',      [\App\Http\Controllers\SystemLogController::class, 'download'])->name('logs.download');
        Route::post('/logs/clear',        [\App\Http\Controllers\SystemLogController::class, 'clearLog'])->name('logs.clear');
    });

    // ── Financial Periods ─────────────────────────────────────────────────────
    Route::prefix('admin/periods')->name('admin.periods.')->group(function () {
        Route::get('/',                            [\App\Http\Controllers\FinancialPeriodController::class, 'index'])->name('index');
        Route::post('/',                           [\App\Http\Controllers\FinancialPeriodController::class, 'store'])->name('store');
        Route::get('/{period}',                    [\App\Http\Controllers\FinancialPeriodController::class, 'show'])->name('show');
        Route::post('/{period}/start-close',       [\App\Http\Controllers\FinancialPeriodController::class, 'startClose'])->name('start-close');
        Route::post('/{period}/provisioning',      [\App\Http\Controllers\FinancialPeriodController::class, 'runProvisioning'])->name('provisioning');
        Route::post('/{period}/facility-interest', [\App\Http\Controllers\FinancialPeriodController::class, 'runFacilityInterest'])->name('facility-interest');
        Route::post('/{period}/bank-recon',        [\App\Http\Controllers\FinancialPeriodController::class, 'markBankRecon'])->name('bank-recon');
        Route::post('/{period}/trial-balance',     [\App\Http\Controllers\FinancialPeriodController::class, 'generateTrialBalance'])->name('trial-balance');
        Route::post('/{period}/close',             [\App\Http\Controllers\FinancialPeriodController::class, 'close'])->name('close');
        Route::post('/{period}/lock',              [\App\Http\Controllers\FinancialPeriodController::class, 'lock'])->name('lock');
    });

    // ── Bank Reconciliation Workspace (full allocation engine) ────────────────
    Route::prefix('admin/finance/recon')->name('admin.finance.recon.')->group(function () {
        Route::get('/{batch}',                 [\App\Http\Controllers\BankAllocationController::class, 'workspace'])->name('workspace');
        Route::post('/{batch}/auto-match',     [\App\Http\Controllers\BankAllocationController::class, 'autoMatch'])->name('auto-match');
        Route::post('/{batch}/allocate',       [\App\Http\Controllers\BankAllocationController::class, 'allocateLine'])->name('allocate');
        Route::post('/{batch}/bulk-allocate',  [\App\Http\Controllers\BankAllocationController::class, 'bulkAllocate'])->name('bulk-allocate');
        Route::post('/{batch}/balances',       [\App\Http\Controllers\BankAllocationController::class, 'updateBalances'])->name('balances');
        Route::post('/{batch}/complete',       [\App\Http\Controllers\BankAllocationController::class, 'markComplete'])->name('complete');
    });

    // ── Company Settings ──────────────────────────────────────────────────────
    Route::get('/admin/settings/company',    [\App\Http\Controllers\CompanySettingsController::class, 'show'])->name('admin.settings.company');
    Route::put('/admin/settings/company',    [\App\Http\Controllers\CompanySettingsController::class, 'update'])->name('admin.settings.company.update');

    // ── Admin 2FA toggle ──────────────────────────────────────────────────────
    Route::post('/admin/settings/2fa/toggle', function (\Illuminate\Http\Request $request) {
        $user = \App\Models\User::findOrFail($request->user_id ?? \Auth::id());
        $user->update(['two_factor_enabled' => !$user->two_factor_enabled]);
        return back()->with('success', '2FA ' . ($user->two_factor_enabled ? 'enabled' : 'disabled') . ' for ' . $user->name . '.');
    })->name('admin.settings.2fa.toggle');

    // ── Nu-Pay imports ────────────────────────────────────────────────────────
    Route::get('/nupay/upload',  [NupayTransactionsStagingController::class, 'showUploadForm'])->name('nupay.upload.form');
    Route::post('/nupay/upload', [NupayTransactionsStagingController::class, 'handleUpload'])
        ->name('nupay.upload')
        ->middleware('throttle:nupay_import');

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
