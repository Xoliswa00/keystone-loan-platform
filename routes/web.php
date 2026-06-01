<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
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

use Illuminate\Support\Facades\Gate;

use function PHPUnit\Framework\isNull;

/*
|---------------------------------------------------------------------------
| Web Routes
|---------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Public route
Route::get('/', function () {
    return view('welcome');
});

// Dashboard route (requires authentication and email verification)

Route::get('/dashboard', function () {
    $user = auth()->user();

    if (is_null($user->rule_id)) {
        return view('dashboard'); // Regular user view
    } else {
        return redirect()->route('admin.dashboard'); // Admin dashboard route
    }
})->middleware(['auth'])->name('dashboard');




// Profile routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});



Route::view('/about', 'about')->name('about');
Route::view('/contact', 'contact')->name('contact');



    
Route::get('/loanapplications', [LoanApplicationController::class, 'index'])->name('loanapplications.show');
Route::put('/loan-applications/{id}/note',
    [LoanApplicationController::class, 'updateNote']
)->name('loanapplications.updateNote');



Route::middleware('auth')->group(function () {


    Route::resource('loan', LoanController::class); // This automatically defines routes like loan.index, loan.create, loan.show, etc.
    Route::resource('loanapplications', LoanApplicationController::class);
    Route::resource('repaymentSchedules', LoanRepaymentController::class);
    Route::resource('transactions', TransactionController::class);
    Route::resource('loaninterests', LoanInterestController::class);
    Route::resource('accountdetails', AccountDetailController::class);
    Route::resource('applications', LoanApplicationController::class);
    Route::resource('loans', LoanController::class);
    Route::resource('customers', CustomerController::class);

        Route::resource('loanrepayments', LoanRepaymentController::class);
                Route::resource('Admin', AdminController::class);


Route::post('loansp/{id}', [LoanController::class, 'approve'])->name('loans.approve');
Route::post('loansr/{id}', [LoanController::class, 'reject'])->name('loans.reject');
Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
Route::post('loans/{loan}/update-payment', [LoanController::class, 'updatePayment'])->name('loans.updatePayment');
// Route definitions
Route::get('/loans', [LoanController::class, 'index'])->name('loans');
Route::get('/loanapplications', [LoanApplicationController::class, 'index'])->name('loanapplications.index');
Route::get('/loanapplications', [LoanApplicationController::class, 'index'])->name('loanapplications.show');

Route::get('/loanrepayments', [LoanRepaymentController::class, 'index'])->name('loanrepayments');





 Route::get('/Admin', [AdminController::class, 'Loans'])->name('admin.loans');
  Route::get('/Admins', [AdminController::class, 'index'])->name('admin.dashboard');
        Route::get('/Admind', [AdminController::class, 'Disbursement'])->name('Admin.Disbursement');
      Route::get('/summary', [AdminController::class, 'summary'])->name('admin.summary');


Route::middleware(['auth'])->prefix('admin/disbursements')->name('disbursements.')->group(function () {
    Route::get('/', [DisbursementController::class, 'index'])->name('index');
    Route::post('/{id}/approve', [DisbursementController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject', [DisbursementController::class, 'reject'])->name('reject');
    Route::post('/{id}/release', [DisbursementController::class, 'release'])->name('release');
    Route::post('/approve-all', [DisbursementController::class, 'approveAll'])->name('approveAll');
});



});


use Illuminate\Support\Facades\Mail;
use App\Mail\LoanNotificationMail;
use App\Models\LoanApplication;


// routes/web.php
Route::get('/terms', [App\Http\Controllers\LoanApplicationController::class, 'terms'])->name('terms.show');


use App\Http\Controllers\NupayTransactionsStagingController;

Route::middleware(['auth'])->group(function () {
    Route::get('/nupay/upload', [NupayTransactionsStagingController::class, 'showUploadForm'])->name('nupay.upload.form');
    Route::post('/nupay/upload', [NupayTransactionsStagingController::class, 'handleUpload'])->name('nupay.upload');
});

use App\Http\Controllers\NupayTransactionController;

Route::prefix('nu-pay/import')->group(function () {
    Route::get('/', [NupayTransactionController::class, 'index'])->name('nu-pay.import.index');
    Route::get('/{importRef}', [NupayTransactionController::class, 'show'])->name('nu-pay.import.show');
    Route::post('/{importRef}/post', [NupayTransactionController::class, 'post'])->name('nu-pay.import.post');
});



Route::prefix('admin/reports')
    ->name('reports.')
    ->middleware(['auth']) // add admin middleware if you have one
    ->group(function () {

        Route::get('/summary', [AdminController::class, 'profitability'])
            ->name('summary');

        Route::get('/portfolio', [AdminController::class, 'portfolio'])
            ->name('portfolio');

        Route::get('/arrears', [AdminController::class, 'arrears'])
            ->name('arrears');

        Route::get('/collections', [AdminController::class, 'collections'])
            ->name('collections');

        Route::get('/disbursements', [AdminController::class, 'disbursements'])
            ->name('disbursements');

        Route::get('/gl-reconciliation', [AdminController::class, 'glReconciliation'])
            ->name('gl-recon');

        Route::get('/reversals', [AdminController::class, 'reversalAudit'])
            ->name('reversals');
            
                      Route::get('/scorecard', [AdminController::class, 'scorecard'])
            ->name('scorecard');

        Route::get('/reviewer/{id}', [AdminController::class, 'reviewer'])
            ->name('reviewer');

        Route::get('/alerts', [AdminController::class, 'alerts'])
            ->name('alerts'); 
            
            
            
            
            
    });


// Authentication routes
require __DIR__.'/auth.php';
