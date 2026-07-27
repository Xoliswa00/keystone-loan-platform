<?php

use App\Http\Controllers\AccountDetailController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\LoanAgreementController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\NupayTransactionController;
use App\Http\Controllers\NupayTransactionsStagingController;
use App\Http\Controllers\PopiaConsentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepaymentScheduleController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────────────────
// Public routes
// ──────────────────────────────────────────────────────────────────────────────

Route::get('/', fn () => view('welcome'));
Route::view('/about', 'about')->name('about');
Route::view('/contact', 'contact')->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contact.store');
Route::get('/terms', [LoanApplicationController::class, 'terms'])->name('terms');
Route::get('/privacy-policy', [LoanApplicationController::class, 'privacyPolicy'])->name('privacy');

// ──────────────────────────────────────────────────────────────────────────────
// Dashboard — redirect admins to admin panel
// ──────────────────────────────────────────────────────────────────────────────

Route::get('/dashboard', function () {
    $user = auth()->user();

    return ! $user->isClient()
        ? redirect()->route('admin.dashboard')
        : view('dashboard');
})->middleware(['auth'])->name('dashboard');

// ──────────────────────────────────────────────────────────────────────────────
// Authenticated — all users
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    // ── Notifications ────────────────────────────────────────────────────────
    Route::post('/notifications/{notification}/read', function (\Illuminate\Http\Request $request, string $notification) {
        $notif = $request->user()->notifications()->findOrFail($notification);
        $notif->markAsRead();

        return back();
    })->name('notifications.read');

    Route::post('/notifications/read-all', function (\Illuminate\Http\Request $request) {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    })->name('notifications.read-all');

    // ── User profile (Laravel Breeze) ────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/consent', [PopiaConsentController::class, 'update'])->name('profile.consent.update');

    // ── Customer financial profile & documents ───────────────────────────────
    Route::prefix('my-profile')->name('customer-profile.')->group(function () {
        Route::get('/', [CustomerProfileController::class, 'show'])->name('show');
        Route::post('/financial', [CustomerProfileController::class, 'saveAffordability'])->name('save-affordability');
        Route::post('/documents', [CustomerProfileController::class, 'uploadDocument'])->name('upload-document');
        Route::get('/pre-qual', [CustomerProfileController::class, 'preQualification'])->name('pre-qual');
    });

    // ── Secure documents (KYC/PII) ───────────────────────────────────────────
    // Every one of these streams from the private 'local' disk; ownership vs.
    // staff-role is checked inside SecureDocumentController, same pattern as
    // the counter-offer route above.
    Route::prefix('documents')->name('secure-documents.')->group(function () {
        Route::get('/customer/{document}',
            [\App\Http\Controllers\SecureDocumentController::class, 'customerDocument'])->name('customer-document');
        Route::get('/application/{application}/{field}',
            [\App\Http\Controllers\SecureDocumentController::class, 'applicationFile'])->name('application-file');
        Route::get('/repayment-proof/{repayment}',
            [\App\Http\Controllers\SecureDocumentController::class, 'repaymentProof'])->name('repayment-proof');
        Route::get('/funding-agreement/{fundingFacility}',
            [\App\Http\Controllers\SecureDocumentController::class, 'fundingAgreement'])->name('funding-agreement');
    });

    // ── Account details (bank accounts) ─────────────────────────────────────
    // Explicit ->parameters() needed: the controller's methods type-hint
    // $accountDetail (camelCase), but Route::resource's auto-derived
    // wildcard for this all-lowercase resource name is {accountdetail} —
    // without this, implicit route model binding silently no-ops (name
    // mismatch), injecting a blank unsaved model instead of the real record.
    Route::resource('accountdetails', AccountDetailController::class)
        ->parameters(['accountdetails' => 'accountDetail']);

    // ── Loan applications (client-facing) ───────────────────────────────────
    Route::resource('loanapplications', LoanApplicationController::class);
    Route::resource('applications', LoanApplicationController::class);
    Route::put('/loan-applications/{id}/note',
        [LoanApplicationController::class, 'updateNote'])->name('loanapplications.updateNote');

    // Counter-offers (client response) — proposing one is staff-only, see
    // the staff group below; ownership is checked inside the controller.
    Route::post('/loan-applications/{application}/counter-offer/accept',
        [\App\Http\Controllers\LoanCounterOfferController::class, 'accept'])->name('counter-offers.accept');
    Route::post('/loan-applications/{application}/counter-offer/decline',
        [\App\Http\Controllers\LoanCounterOfferController::class, 'decline'])->name('counter-offers.decline');

    // ── Loans (client-facing) ────────────────────────────────────────────────
    // Only index is client-facing (scoped to the authenticated user's own
    // loans). show/edit/update/destroy/update-payment mutate or expose
    // records by raw ID with no ownership check and touch fields
    // (approved_amount, remaining_balance) that must go through the
    // approval/disbursement/GL-posting flow — moved to the staff group below.
    Route::resource('loan', LoanController::class)->only(['index']);

    // ── Repayment schedules & repayments (client: view only; mutation is
    //    staff-only — see the staff group below) ───────────────────────────
    // ->whereNumber(): this 'show' route is registered here (client group)
    // before the staff group below registers 'create'/'edit' for the same
    // resource (split via only()/except() so clients can't reach
    // create/store/update/destroy). Without a numeric constraint, the
    // literal segment "create" or "edit" would match {repaymentSchedule}/
    // {loanRepayment} first (routes match in registration order) and
    // attempt to load a record with that literal string as its ID.
    Route::resource('repaymentSchedules', RepaymentScheduleController::class)
        ->only(['index', 'show'])
        ->whereNumber('repaymentSchedule');
    // Client-side proof of payment for a manual payment that didn't come
    // through NuPay — ownership checked in the controller. Sits pending
    // until staff verify it (see the staff group below).
    Route::post('/repayment-schedules/{repaymentSchedule}/proof-of-payment',
        [LoanRepaymentController::class, 'submitProof'])->name('repaymentSchedules.submit-proof');
    // Explicit ->parameters(): see the accountdetails comment below the
    // staff group — same case-mismatch class ({loanrepayment} vs
    // controller's $loanRepayment) silently breaks implicit model binding.
    Route::resource('loanrepayments', LoanRepaymentController::class)
        ->only(['index', 'show'])
        ->parameters(['loanrepayments' => 'loanRepayment'])
        ->whereNumber('loanRepayment');

    // ── NCA Agreements (accessible to both client and admin) ─────────────────
    Route::prefix('agreements')->name('agreements.')->group(function () {
        Route::get('/application/{application}', [LoanAgreementController::class, 'index'])->name('index');
        Route::get('/download/{agreement}', [LoanAgreementController::class, 'download'])->name('download');
        Route::post('/accept/{agreement}', [LoanAgreementController::class, 'accept'])->name('accept');
    });

    // Settlement quote (NCA s.125) — a client's own legal right to request
    // one on their own loan, not a staff-only action; ownership is checked
    // in the controller the same way download()/accept() above are. Was
    // previously registered only inside the staff-only role group below,
    // so the "Request Settlement Quote" button on the client's own loan
    // page (resources/views/loans/show.blade.php) 403'd for every client.
    Route::post('/admin/agreements/{loan}/settlement-quote',
        [LoanAgreementController::class, 'generateSettlementQuote'])->name('admin.agreements.settlement-quote');

    // ── Transactions ──────────────────────────────────────────────────────────
    // create/store removed — orphaned scaffold that never matched the real
    // schema (form posted 'type'=credit/debit; the transactions table's
    // actual column is the transaction_type enum(disbursement,repayment,
    // penalty)), unscoped to any loan/user, and its only UI link was
    // already commented out. index/show/edit/update/destroy stay — those
    // are correctly ownership-scoped and still linked from the UI.
    Route::resource('transactions', TransactionController::class)->except(['create', 'store']);
    // loaninterests moved to the staff group below — no legitimate client
    // use case for setting/overriding a loan's interest rate directly, and
    // it wrote records for any loan_id with no ownership check.

    // ── Statement download (async) ────────────────────────────────────────────
    Route::get('/my-statement', [\App\Http\Controllers\ClientStatementController::class, 'myStatement'])->name('client.my-statement');
    Route::get('/my-statement/status', [\App\Http\Controllers\ClientStatementController::class, 'checkStatus'])->name('client.statement.status');
    Route::get('/statements/{user}/{period}', [\App\Http\Controllers\ClientStatementController::class, 'serveFile'])->name('client.statement.file');
});

// ──────────────────────────────────────────────────────────────────────────────
// Admin — role_id === 2 required
// All admin routes sit behind a middleware check. Add a dedicated `admin`
// middleware class when ready; for now we use a closure middleware inline.
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'role:admin,loan_officer,finance,it_admin'])->group(function () {

    // ── Dashboards & overview ────────────────────────────────────────────────
    Route::get('/Admins', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/Admin', [AdminController::class, 'Loans'])->name('admin.loans');
    // Legacy duplicate of disbursements.index (unlinked from any nav/view) —
    // its buttons already post to the now-restricted disbursements.* routes
    // below, so gate the page itself the same way to avoid a dead-button
    // page for finance.
    Route::middleware('role:admin,loan_officer,it_admin')
        ->get('/Admind', [AdminController::class, 'Disbursement'])->name('Admin.Disbursement');
    Route::get('/summary', [AdminController::class, 'summary'])->name('admin.summary');

    // ── Application management ───────────────────────────────────────────────
    Route::get('/admin/applications', [LoanApplicationController::class, 'adminIndex'])
        ->name('admin.applications.index');

    // Approve / reject actions (POST to avoid accidental GET triggers) —
    // loan officer's exclusive "loan movement" remit, same reasoning as the
    // recon/import groups excluding loan_officer above but mirrored: finance
    // is excluded here. it_admin keeps break-glass access, same as elsewhere.
    Route::middleware('role:admin,loan_officer,it_admin')->group(function () {
        Route::post('admin/loans/{id}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('admin/loans/{id}/reject', [LoanController::class, 'reject'])->name('loans.reject');
        Route::post('admin/loans/bulk-approve', [LoanController::class, 'bulkApprove'])->name('loans.bulkApprove');
        Route::post('admin/loans/bulk-reject', [LoanController::class, 'bulkReject'])->name('loans.bulkReject');
        Route::post('admin/loans/{id}/reverse', [LoanController::class, 'reverseApproval'])->name('loans.reverse');
        // update()/updatePayment() write approved_amount/remaining_balance
        // directly with no GL posting — same "loan movement" remit as
        // approve/reject/reverse above, so finance is excluded here too.
        Route::put('loan/{loan}', [LoanController::class, 'update'])->name('loans.update');
        Route::post('loan/{loan}/update-payment', [LoanController::class, 'updatePayment'])
            ->name('loans.updatePayment');
    });

    // Propose a counter-offer on an application stuck in affordability_review
    Route::post('admin/loan-applications/{application}/counter-offer',
        [\App\Http\Controllers\LoanCounterOfferController::class, 'store'])->name('admin.counter-offers.store');

    // Staff-only loan record management (see client group above for why
    // these aren't client-facing). 'update' is excluded here — it's
    // registered above under the narrower loan_officer/it_admin/admin
    // group alongside updatePayment(), matching how approve/reject/reverse
    // are already scoped away from finance. 'edit'/'destroy' are removed
    // entirely — both were unlinked from any view (a shadow-endpoint audit
    // found them reachable by direct request only), 'edit' rendered the
    // wrong view (undefined-variable error), and 'destroy' archived a loan
    // with zero GL reversal or balance check.
    Route::resource('loan', LoanController::class)->except(['index', 'create', 'store', 'update', 'edit', 'destroy']);
    // The loaninterests resource that used to sit here was removed by a
    // shadow-endpoint audit: unlinked from any view (not even its own
    // index/show — nothing outside loan_interests/* itself referenced it),
    // its own view called it "reference only", and it carried the same
    // implicit-binding bug described below (Route::resource derives
    // {loaninterest} — all lowercase — from the resource string, but the
    // controller type-hints the camelCase $loanInterest; with neither an
    // exact nor a Str::snake() match, implicit binding silently skipped
    // resolution and handed the controller a blank, unsaved model instead
    // of throwing 404 or loading the real record). loanrepayments below
    // has the same naming mismatch, which is why it still needs
    // ->parameters() explicitly.
    Route::resource('repaymentSchedules', RepaymentScheduleController::class)->except(['index', 'show']);
    Route::resource('loanrepayments', LoanRepaymentController::class)
        ->except(['index', 'show'])
        ->parameters(['loanrepayments' => 'loanRepayment']);

    // ── Customers ────────────────────────────────────────────────────────────
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/restrict', [CustomerController::class, 'restrict'])->name('customers.restrict');
    Route::post('customers/{customer}/lift', [CustomerController::class, 'lift'])->name('customers.lift');
    Route::post('customers/{customer}/blacklist', [CustomerController::class, 'toggleBlacklist'])->name('customers.blacklist');
    Route::post('customers/{customer}/extended-terms', [CustomerController::class, 'toggleExtendedTerms'])->name('customers.extended-terms');

    // ── Loan Products (rates/fees/terms configuration) ───────────────────────
    // index/show are informational — visible to the full staff group so loan
    // officers can see what's on offer. Mutating actions live in the tighter
    // role:admin,finance,it_admin group below — changing rates/fees/term
    // limits is a finance decision, not a loan officer one.
    // ->parameters()/->whereNumber(): same route-model-binding pitfalls
    // documented above for loanrepayments/loaninterests/accountdetails —
    // {loanProduct} must match the controller's camelCase type-hint, and the
    // 'show' route (registered here, before 'create' below) needs a numeric
    // constraint so the literal segment "create" can't match it first.
    Route::resource('loan-products', LoanProductController::class)
        ->only(['index', 'show'])
        ->parameters(['loan-products' => 'loanProduct'])
        ->whereNumber('loanProduct');

    // ── NCA Agreement generation (admin only) ─────────────────────────────────
    // (settlement-quote moved to the shared client/staff group above — see
    // the comment there.)
    Route::post('/admin/agreements/{application}/pre-agreement',
        [LoanAgreementController::class, 'generatePreAgreement'])->name('admin.agreements.pre-agreement');
    Route::post('/admin/agreements/{application}/loan-agreement',
        [LoanAgreementController::class, 'generateLoanAgreement'])->name('admin.agreements.loan-agreement');
    Route::get('/admin/agreements/{application}/preview/{type?}',
        [LoanAgreementController::class, 'preview'])->name('admin.agreements.preview');

    // ── Disbursements — loan movement, same finance-excluded remit as loan
    // approval above (no officer/finance segregation-of-duties split here). ──
    Route::middleware('role:admin,loan_officer,it_admin')->prefix('admin/disbursements')->name('disbursements.')->group(function () {
        Route::get('/', [DisbursementController::class, 'index'])->name('index');
        Route::post('/{id}/approve', [DisbursementController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [DisbursementController::class, 'reject'])->name('reject');
        Route::post('/{id}/release', [DisbursementController::class, 'release'])->name('release');
        Route::post('/approve-all', [DisbursementController::class, 'approveAll'])->name('approveAll');
    });

    // ── Document verification — loan officer's "verifications" remit; finance
    // is excluded the same way it's excluded from loan approval/disbursement.
    Route::middleware('role:admin,loan_officer,it_admin')
        ->post('admin/documents/{document}/verify',
            [CustomerProfileController::class, 'verifyDocument'])->name('admin.documents.verify');

    // ── Funding Facilities, Business Bank Statement, Financial Periods, Bank
    // Recon Workspace — all restricted to admin/finance/it_admin (see the
    // comment on the lending-settings group above for why loan_officer is
    // excluded: these move/recognise money and don't belong to loan
    // origination). Matches finance's exclusive "recons and imports of
    // financial data" remit.
    Route::middleware('role:admin,finance,it_admin')->group(function () {
        Route::prefix('admin/funding')->name('admin.funding.')->group(function () {
            Route::get('/', [\App\Http\Controllers\FundingFacilityController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\FundingFacilityController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\FundingFacilityController::class, 'store'])->name('store');
            Route::get('/{fundingFacility}', [\App\Http\Controllers\FundingFacilityController::class, 'show'])->name('show');
            Route::get('/{fundingFacility}/edit', [\App\Http\Controllers\FundingFacilityController::class, 'edit'])->name('edit');
            Route::put('/{fundingFacility}', [\App\Http\Controllers\FundingFacilityController::class, 'update'])->name('update');
            Route::post('/{fundingFacility}/transaction', [\App\Http\Controllers\FundingFacilityController::class, 'recordTransaction'])->name('transaction');
            Route::post('/accrue-all', [\App\Http\Controllers\FundingFacilityController::class, 'accrueAll'])->name('accrue-all');
            Route::post('/{fundingFacility}/upload-agreement', [\App\Http\Controllers\FundingFacilityController::class, 'uploadAgreement'])->name('upload-agreement');
        });

        Route::prefix('admin/finance')->name('admin.finance.')->group(function () {
            Route::get('/business-bank', [\App\Http\Controllers\BusinessBankStatementController::class, 'showUpload'])->name('business-bank.upload');
            Route::post('/business-bank', [\App\Http\Controllers\BusinessBankStatementController::class, 'handleUpload'])->name('business-bank.store');
            Route::get('/business-bank/{batch}', [\App\Http\Controllers\BusinessBankStatementController::class, 'show'])->name('business-bank.show');
            Route::post('/business-bank/{batch}/reconcile', [\App\Http\Controllers\BusinessBankStatementController::class, 'reconcile'])->name('business-bank.reconcile');
        });

        // ── Financial Periods ─────────────────────────────────────────────────
        Route::prefix('admin/periods')->name('admin.periods.')->group(function () {
            Route::get('/', [\App\Http\Controllers\FinancialPeriodController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\FinancialPeriodController::class, 'store'])->name('store');
            Route::get('/{period}', [\App\Http\Controllers\FinancialPeriodController::class, 'show'])->name('show');
            Route::post('/{period}/start-close', [\App\Http\Controllers\FinancialPeriodController::class, 'startClose'])->name('start-close');
            Route::post('/{period}/provisioning', [\App\Http\Controllers\FinancialPeriodController::class, 'runProvisioning'])->name('provisioning');
            Route::post('/{period}/facility-interest', [\App\Http\Controllers\FinancialPeriodController::class, 'runFacilityInterest'])->name('facility-interest');
            Route::post('/{period}/bank-recon', [\App\Http\Controllers\FinancialPeriodController::class, 'markBankRecon'])->name('bank-recon');
            Route::post('/{period}/bank-recon-no-activity', [\App\Http\Controllers\FinancialPeriodController::class, 'markBankReconNoActivity'])->name('bank-recon-no-activity');
            Route::post('/{period}/trial-balance', [\App\Http\Controllers\FinancialPeriodController::class, 'generateTrialBalance'])->name('trial-balance');
            Route::post('/{period}/close', [\App\Http\Controllers\FinancialPeriodController::class, 'close'])->name('close');
            Route::post('/{period}/lock', [\App\Http\Controllers\FinancialPeriodController::class, 'lock'])->name('lock');
        });

        // ── Bank Reconciliation Workspace (full allocation engine) ─────────────
        Route::prefix('admin/finance/recon')->name('admin.finance.recon.')->group(function () {
            Route::get('/{batch}', [\App\Http\Controllers\BankAllocationController::class, 'workspace'])->name('workspace');
            Route::post('/{batch}/auto-match', [\App\Http\Controllers\BankAllocationController::class, 'autoMatch'])->name('auto-match');
            Route::post('/{batch}/allocate', [\App\Http\Controllers\BankAllocationController::class, 'allocateLine'])->name('allocate');
            Route::post('/{batch}/bulk-allocate', [\App\Http\Controllers\BankAllocationController::class, 'bulkAllocate'])->name('bulk-allocate');
            Route::post('/{batch}/unallocate', [\App\Http\Controllers\BankAllocationController::class, 'unallocateLine'])->name('unallocate');
            Route::post('/{batch}/balances', [\App\Http\Controllers\BankAllocationController::class, 'updateBalances'])->name('balances');
            Route::post('/{batch}/complete', [\App\Http\Controllers\BankAllocationController::class, 'markComplete'])->name('complete');
        });
    });

    // ── System Logs ───────────────────────────────────────────────────────────
    Route::prefix('admin/system')->name('admin.system.')->group(function () {
        Route::get('/logs', [\App\Http\Controllers\SystemLogController::class, 'index'])->name('logs');
        Route::get('/logs/download', [\App\Http\Controllers\SystemLogController::class, 'download'])->name('logs.download');

        // Destructive/operational actions — narrowed to match the
        // read-only audit-log's own role gate (reports.audit-log, below).
        // These were previously reachable by loan_officer/finance too,
        // meaning a role explicitly barred from *viewing* the compliance
        // audit trail could nonetheless truncate failed_jobs or clear the
        // live application log — destroying evidence gated more loosely
        // than reading it.
        Route::middleware('role:admin,it_admin')->group(function () {
            Route::post('/logs/retry', [\App\Http\Controllers\SystemLogController::class, 'retryJob'])->name('logs.retry');
            Route::post('/logs/clear-failed', [\App\Http\Controllers\SystemLogController::class, 'clearFailed'])->name('logs.clear-failed');
            Route::post('/logs/clear', [\App\Http\Controllers\SystemLogController::class, 'clearLog'])->name('logs.clear');
            Route::post('/jobs/run', [\App\Http\Controllers\SystemLogController::class, 'runJob'])->name('jobs.run');
        });
    });

    // ── Company Settings ──────────────────────────────────────────────────────
    Route::get('/admin/settings/company', [\App\Http\Controllers\CompanySettingsController::class, 'show'])->name('admin.settings.company');
    Route::put('/admin/settings/company', [\App\Http\Controllers\CompanySettingsController::class, 'update'])->name('admin.settings.company.update');

    // ── Lending & Risk Settings ────────────────────────────────────────────────
    // Restricted to 'admin'/'finance'/'it_admin' — these thresholds directly
    // govern lending decisions, IFRS 9 provisioning, and risk scoring, unlike
    // company/NCR contact details above, so loan_officer is excluded here.
    Route::middleware('role:admin,finance,it_admin')->group(function () {
        Route::get('/admin/settings/lending', [\App\Http\Controllers\LendingSettingsController::class, 'show'])->name('admin.settings.lending');
        Route::put('/admin/settings/lending', [\App\Http\Controllers\LendingSettingsController::class, 'update'])->name('admin.settings.lending.update');
    });

    // ── Admin 2FA toggle ──────────────────────────────────────────────────────
    // Restricted to 'admin' — disabling another staff member's 2FA is a
    // sensitive action that must not be available to every staff role in
    // the surrounding group (loan_officer, finance, it_admin).
    Route::post('/admin/settings/2fa/toggle', function (\Illuminate\Http\Request $request) {
        $user = \App\Models\User::findOrFail($request->user_id ?? \Auth::id());
        $new = ! $user->two_factor_enabled;
        // forceFill(), not update() — two_factor_enabled isn't in
        // User::$fillable, so update() silently no-ops and this toggle has
        // never actually persisted (the flash message always reported the
        // original, unchanged state).
        $user->forceFill(['two_factor_enabled' => $new])->save();

        \App\Models\AuditLog::record($new ? '2fa_enabled' : '2fa_disabled', $user, [], []);

        return back()->with('success', '2FA '.($new ? 'enabled' : 'disabled').' for '.$user->name.'.');
    })->middleware('role:admin')->name('admin.settings.2fa.toggle');

    // ── Staff Management ──────────────────────────────────────────────────────
    // Restricted to 'admin'/'it_admin' — adding staff and changing roles is
    // more sensitive than the surrounding group's loan_officer/finance access.
    Route::prefix('admin/staff')->name('admin.staff.')->middleware('role:it_admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\StaffController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\StaffController::class, 'store'])->name('store');
        Route::put('/{user}', [\App\Http\Controllers\StaffController::class, 'updateRole'])->name('update-role');
    });

    // Restricted to 'admin'/'finance'/'it_admin' — changing loan product
    // rates, fees, and term limits is a finance decision; loan_officer (in
    // the surrounding group) can still view products via index/show above.
    Route::middleware('role:admin,finance,it_admin')->group(function () {
        // No destroy — products are referenced by historical loans/
        // applications; deactivating via toggle-active is the supported
        // way to retire one, not deletion.
        Route::resource('loan-products', LoanProductController::class)
            ->except(['index', 'show', 'destroy'])
            ->parameters(['loan-products' => 'loanProduct']);
        Route::post('loan-products/{loanProduct}/toggle-active', [LoanProductController::class, 'toggleActive'])
            ->name('loan-products.toggle-active');
    });

    // ── Nu-Pay imports — finance's exclusive "imports of financial data"
    // remit; these post directly to the GL (see GLPostingService), so
    // loan_officer is excluded the same way as the funding/periods/recon
    // groups above.
    Route::middleware('role:admin,finance,it_admin')->group(function () {
        Route::get('/nupay/upload', [NupayTransactionsStagingController::class, 'showUploadForm'])->name('nupay.upload.form');
        Route::post('/nupay/upload', [NupayTransactionsStagingController::class, 'handleUpload'])
            ->name('nupay.upload')
            ->middleware('throttle:nupay_import');

        Route::prefix('nu-pay/import')->name('nu-pay.import.')->group(function () {
            Route::get('/', [NupayTransactionController::class, 'index'])->name('index');
            Route::get('/{importRef}', [NupayTransactionController::class, 'show'])->name('show');
            Route::post('/{importRef}/post', [NupayTransactionController::class, 'post'])->name('post');
            // {transaction} binds to nupay_transactions_staging — separate from
            // {importRef} above, which is a plain string (the batch's import_ref)
            Route::put('/{importRef}/transactions/{transaction}', [NupayTransactionController::class, 'updateTransaction'])->name('transactions.update');
        });
    });

    // ── Reports ───────────────────────────────────────────────────────────────
    // Cross-cutting operational reports — loan officer's day-to-day (arrears,
    // collections, portfolio, scorecard) but harmless/useful for finance and
    // IT to see too, so left in the shared staff group.
    Route::prefix('admin/reports')->name('reports.')->group(function () {
        Route::get('/portfolio', [AdminController::class, 'portfolio'])->name('portfolio');
        Route::get('/arrears', [AdminController::class, 'arrears'])->name('arrears');
        Route::get('/collections', [AdminController::class, 'collections'])->name('collections');
        Route::get('/disbursements', [AdminController::class, 'disbursements'])->name('disbursements');
        Route::get('/scorecard', [AdminController::class, 'scorecard'])->name('scorecard');
        Route::get('/reviewer/{id}', [AdminController::class, 'reviewer'])->name('reviewer');
        Route::get('/alerts', [AdminController::class, 'alerts'])->name('alerts');

        // ── Finance-specific — profitability, GL, and the statutory/
        // financial-statement reports live under finance's exclusive recon
        // remit, same as the Nu-Pay/funding/periods groups above.
        Route::middleware('role:admin,finance,it_admin')->group(function () {
            Route::get('/summary', [AdminController::class, 'profitability'])->name('summary');
            Route::get('/profitability', [AdminController::class, 'profitability'])->name('profitability');
            Route::get('/gl-reconciliation', [AdminController::class, 'glReconciliation'])->name('gl-recon');
            Route::get('/gl-summary', [AdminController::class, 'glReconciliation'])->name('gl_summary');
            Route::get('/reversals', [AdminController::class, 'reversalAudit'])->name('reversals');
            Route::get('/ncr-export', [\App\Http\Controllers\AdminOpsController::class, 'ncrExportDownload'])->name('ncr-export');
            Route::get('/vat-201', [AdminController::class, 'vat201'])->name('vat201');
            Route::get('/income-statement', [AdminController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/balance-sheet', [AdminController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/write-off-register', [AdminController::class, 'writeOffRegister'])->name('write-off-register');
            Route::get('/npl', [AdminController::class, 'nplReport'])->name('npl');
        });

        // ── IT's exclusive "monitors everyone's audit report" remit ────────────
        Route::middleware('role:admin,it_admin')->group(function () {
            Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit-log');
        });
    });

    // ── Bank statement import — finance's recon remit, same as Nu-Pay/
    // business-bank/funding/periods above ──────────────────────────────────
    Route::middleware('role:admin,finance,it_admin')->group(function () {
        Route::get('/bank-statement/upload', [\App\Http\Controllers\BankStatementController::class, 'showUpload'])->name('bank-statement.upload');
        Route::post('/bank-statement/upload', [\App\Http\Controllers\BankStatementController::class, 'handleUpload'])->name('bank-statement.store');
        Route::get('/bank-statement/{batch}', [\App\Http\Controllers\BankStatementController::class, 'show'])->name('bank-statement.show');
        Route::post('/bank-statement/{batch}/reconcile', [\App\Http\Controllers\BankStatementController::class, 'reconcile'])->name('bank-statement.reconcile');
    });

    // ── Client statement PDF ──────────────────────────────────────────────────
    // client.my-statement is NOT re-registered here — it already exists in
    // the plain client auth group above (Route::get('/my-statement', ...) —
    // scoped to Auth::user() internally, no {user} param, so no legitimate
    // reason to also gate it behind staff roles). Registering the same
    // method+URI twice doesn't add a second route: Laravel's RouteCollection
    // keys routes by method+URI, so this second registration silently
    // replaced the first, meaning every client hit the role check below and
    // got 403'd trying to view their own statement.
    Route::get('/client/statement/{user}', [\App\Http\Controllers\ClientStatementController::class, 'download'])->name('client.statement');

    // ── Admin operations: notes + document verification ───────────────────────
    Route::post('/admin/notes/{application}', [\App\Http\Controllers\AdminOpsController::class, 'addNote'])->name('admin.notes.store');
    Route::post('/admin/loan-applications/{application}/reassess', [\App\Http\Controllers\AdminOpsController::class, 'reassess'])->name('admin.applications.reassess');
    Route::post('/admin/assign/{application}', [\App\Http\Controllers\AdminOpsController::class, 'assign'])->name('admin.assign');
    Route::post('/admin/collections/log', [\App\Http\Controllers\AdminOpsController::class, 'logContactAttempt'])->name('admin.collections.log');
    Route::get('/admin/collections', [\App\Http\Controllers\AdminOpsController::class, 'collectionsQueue'])->name('admin.collections');

    // ── Manual payment verification — client-submitted proof of payment
    // needs staff sign-off before it's treated as paid/posted to GL. Loan
    // officer's "verifications" remit, same restriction as document
    // verification (finance excluded).
    Route::middleware('role:admin,loan_officer,it_admin')->prefix('admin/manual-payments')->name('admin.manual-payments.')->group(function () {
        Route::get('/', [LoanRepaymentController::class, 'pendingVerification'])->name('index');
        Route::post('/{loanRepayment}/approve', [LoanRepaymentController::class, 'approveManualPayment'])->name('approve');
        Route::post('/{loanRepayment}/reject', [LoanRepaymentController::class, 'rejectManualPayment'])->name('reject');
    });

    // Reverse an already-posted payment (manual OR NuPay origin — not
    // limited to ones that went through the verification queue above), same
    // role remit as payment verification.
    Route::middleware('role:admin,loan_officer,it_admin')
        ->post('admin/payments/{loanRepayment}/reverse', [LoanRepaymentController::class, 'reversePayment'])
        ->name('admin.payments.reverse');

    // ── NCR export (download) — same finance-only restriction as
    // reports.ncr-export above; this is a legacy duplicate route to the same
    // controller method, gated identically so it isn't a bypass.
    Route::middleware('role:admin,finance,it_admin')
        ->get('/admin/ncr-export', [\App\Http\Controllers\AdminOpsController::class, 'ncrExportDownload'])->name('admin.ncr-export');

    // ── Debt recovery ─────────────────────────────────────────────────────────
    Route::prefix('admin/recovery')->name('admin.recovery.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DebtRecoveryController::class, 'index'])->name('index');
        Route::get('/{recovery}', [\App\Http\Controllers\DebtRecoveryController::class, 'show'])->name('show');
        Route::post('/open', [\App\Http\Controllers\DebtRecoveryController::class, 'open'])->name('open');
        Route::post('/{recovery}/payment', [\App\Http\Controllers\DebtRecoveryController::class, 'recordPayment'])->name('payment');
        Route::patch('/{recovery}', [\App\Http\Controllers\DebtRecoveryController::class, 'update'])->name('update');
    });

    // ── Admin resource (legacy) ───────────────────────────────────────────────
    // Only 'show' is real (used by loan_applications review pages as
    // Admin.show) — index/create/store/edit/update/destroy were leftover
    // resource scaffolding with empty stub bodies, and 'index' silently
    // clobbered the real /Admin -> admin.loans route registered above
    // (Laravel keeps the last-registered route for a given method+URI).
    Route::resource('Admin', AdminController::class)->only(['show']);
});

// ──────────────────────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';
