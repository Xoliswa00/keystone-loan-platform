<?php

namespace App\Services;

use App\Models\{
    arbatch,
    arbatch_entries,
    Customer,
    Loan,
    LoanSchedule,
    nupay_transactions_staging,
    nupay_transaction,
    gl_accounts,
    glmapping,
    User,
    LoanFee ,
    import_batch,RepaymentSchedule
};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class NuPayService
{
    protected GLPostingService $glPosting;

    protected float $feeRate = 0.02;
    protected float $lateFeeRate = 0.05; // future use

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    public function postTransaction(int $stagingId, int $userId): arbatch
    {
        return DB::transaction(function () use ($stagingId, $userId) {

            $txn = nupay_transactions_staging::lockForUpdate()->findOrFail($stagingId);
            $transactionType = strtolower($txn->transaction_type ?? 'success');

            /* ---------------- Customer Resolution ---------------- */
            // 🔎 REVIEW: Prevent fatal errors if ID not found
            $user = User::where('ID_Number', $txn->debtor_id)->first();
            
            if (!$user) {
                throw new Exception("User not found for debtor_id {$txn->debtor_id}");
            }
            
            $customer = Customer::where('user_id', $user->id)
                ->lockForUpdate() // 🔎 REVIEW: lock to protect balance updates
                ->first();
            
            if (!$customer) {
                throw new Exception("Customer not found for user {$user->id}");
            }
            
            $customerResolved = true;


            

            /* ---------------- Amounts ---------------- */
            $grossAmount = round($txn->instalment_amount, 2);
            $feeAmount   = round($grossAmount * $this->feeRate, 2);
            $lateFee     = round($grossAmount * $this->lateFeeRate, 2);

            /* ---------------- AR Batch ---------------- */
            $arBatch = arbatch::create([
                'reference'    => 'ARB-NUPAY-' . now()->format('YmdHis') . '-' . $txn->id,
                'customer_id'  => $customer->id ,
                'source_type'  => nupay_transactions_staging::class,
                'source_id'    => $txn->id,
                'total_amount' => $txn->instalment_amount,
                'status'       => 'approved',
                'created_by'   => $userId,
                'approved_by'  => $userId,
                'approved_at'  => now(),
            ]);

            /* ---------------- GL Resolution ---------------- */
            $bankGl           = $this->getGlAccountFor('loan_repayment_dr');
            $loanControlGl    = $this->getGlAccountFor('loan_disbursement_dr');
            $interestIncomeGl = $this->getGlAccountFor('interest_income');
            $feeExpenseGl     = $this->getGlAccountFor('bank_charges');
            $penaltyIncomeGl  = $this->getGlAccountFor('penalty_income');

            $entries = [];

            /* ======================================================
               Transaction-Type Logic
            ====================================================== */
            switch ($transactionType) {

                /* ===================== SUCCESS ===================== */
                case 'success':

                    if (!$customerResolved) {
                        throw new Exception("NuPay success cannot be posted without resolved customer");
                    }

                    $allocation = $this->allocateLoanRepayment($customer, $txn);

                    $loan            = $allocation['loan'];
                    $schedule = $allocation['schedule'];
                    $principalAmount = $allocation['principal'];
                    $interestAmount  = $allocation['interest'];

                    /* 1️⃣ Bank receives FULL customer payment */
                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $bankGl->id,
                        'entry_type'    => 'debit',
                        'amount'        => $grossAmount,
                        'description'   => "NuPay repayment received #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    /* 2️⃣ Loan principal cleared */
                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $loanControlGl->id,
                        'entry_type'    => 'credit',
                        'amount'        => $principalAmount,
                        'description'   => "Loan principal repayment #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    /* 3️⃣ Interest income recognised */
                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $interestIncomeGl->id,
                        'entry_type'    => 'credit',
                        'amount'        => $interestAmount,
                        'description'   => "Loan interest income #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    /* 4️⃣ NuPay service fee */
                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $feeExpenseGl->id,
                        'entry_type'    => 'debit',
                        'amount'        => $feeAmount,
                        'description'   => "NuPay service fee #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $bankGl->id,
                        'entry_type'    => 'credit',
                        'amount'        => $feeAmount,
                        'description'   => "NuPay fee deduction #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    $loan->decrement('remaining_balance', $txn->instalment_amount);
                    $customer->decrement('current_balance', $txn->instalment_amount);
                    $loan->update([
                                'status'=>'Settled'
                                ]);
                    $schedule->update([
                        'status'  => 'paid',
                        'paid_at' => now(),
                    ]);


                    break;

                /* ================= FAILED / CANCELLED ================= */
                case 'failed':
                case 'canceled':
                        $allocation = $this->allocateLoanRepayment($customer, $txn);
                        $loan       = $allocation['loan'];
                        $schedule   = $allocation['schedule'];

                    if ($customerResolved) {
                        $entries[] = [
                            'arbatch_id'    => $arBatch->id,
                            'gl_account_id' => $loanControlGl->id,
                            'entry_type'    => 'debit',
                            'amount'        => $lateFee,
                            'description'   => "Late payment penalty #{$txn->id}",
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];

                        $entries[] = [
                            'arbatch_id'    => $arBatch->id,
                            'gl_account_id' => $penaltyIncomeGl->id,
                            'entry_type'    => 'credit',
                            'amount'        => $lateFee,
                            'description'   => "Penalty income #{$txn->id}",
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }
                    $loan->update([
                                'status'=>'Payment Failed'
                                ]);
                           
             $schedule->update([
                        'status'  => 'Failed',
                    ]);


                    break;

                /* ===================== REVERSED ===================== */
                case 'reversed':
                           $allocation = $this->allocateLoanRepayment($customer, $txn);
                        $loan       = $allocation['loan'];
                        $schedule   = $allocation['schedule'];

                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $loanControlGl->id,
                        'entry_type'    => 'debit',
                        'amount'        => $grossAmount,
                        'description'   => "NuPay reversal – loan #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    $entries[] = [
                        'arbatch_id'    => $arBatch->id,
                        'gl_account_id' => $bankGl->id,
                        'entry_type'    => 'credit',
                        'amount'        => $grossAmount,
                        'description'   => "NuPay reversal – bank #{$txn->id}",
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                    $loan->update([ 'status'=>'Reversed'
            ]);
              $schedule->update([
                        'status'  => 'Reversed',
                    ]);

                    break;

                default:
                    throw new Exception("Unsupported NuPay transaction type: {$transactionType}");
            }

            /* ---------------- Persist & Post ---------------- */
            
           //dd($arBatch,$entries);
            
            arbatch_entries::insert($entries);
            $this->glPosting->postArBatch($arBatch, $userId);

            /* ---------------- Finalise ---------------- */
            $batch = import_batch::where('import_ref', $txn->import_ref)->firstOrFail();

            $txn->update([
                'import_id' => $batch->id,
                'posted_at' => now(),
            ]);

            $batch->update([
                'status' => 'PROCESSED',
            ]);
            
            

            

            nupay_transaction::updateOrCreate(
                [
                    'import_ref' => $txn->import_ref,
                    'mandate_id' => $txn->mandate_id,
                ],
                [
                    'debtor_id'        => $txn->debtor_id,
                    'amount'           => $grossAmount,
                    'fee'              => $feeAmount,
                    'net_amount'       => $grossAmount - $feeAmount,
                    'transaction_type' => $transactionType,
                    'posted_at'        => now(),
                ]
            );

            return $arBatch;
        });
    }

    /* ==========================================================
       Allocation Helper (Schedule-driven, interest-first)
    ========================================================== */
    protected function allocateLoanRepayment(Customer $customer, nupay_transactions_staging $txn): array
    {
    
        $loan = Loan::where('user_id', $customer->user_id)
            ->whereIn('status', ['disbursed','Payment Failed'])
            ->lockForUpdate()
            ->firstOrFail();


$loanFeesTotal = LoanFee::where('loan_application_id', $loan->loan_application_id)
    ->selectRaw('
        SUM(
            COALESCE(interest_amount, 0) +
            COALESCE(initiation_fee, 0) +
            COALESCE(service_fee, 0)
        ) AS total_fees
    ')
    ->value('total_fees');

        $monthStart = Carbon::parse($txn->action_date)->startOfMonth();
     $schedule = RepaymentSchedule::where('loan_id', $loan->loan_application_id)
    ->whereBetween('due_date', [
        Carbon::parse($txn->action_date)->startOfMonth(),
        Carbon::parse($txn->action_date)->endOfMonth()
    ])
    ->lockForUpdate()
    ->firstOrFail();

        $scheduledPrincipal = round($loan->approved_amount, 2);
        $scheduledInterest  = round($loanFeesTotal, 2);
        $paidAmount         = round($txn->instalment_amount, 2);

        $interest  = $loanFeesTotal;
        $principal = max(0, $scheduledPrincipal);

        return [
            'loan'      => $loan,
            'schedule'  => $schedule,
            'principal' => round($principal, 2),
            'interest'  => round($interest, 2),
        ];
    }

    /* ==========================================================
       GL Resolver
    ========================================================== */
    protected function getGlAccountFor(string $key)
    {
        $mapping = glmapping::where('key', $key)
            ->where('is_active', 1)
            ->firstOrFail();

        return gl_accounts::whereHas('chartOfAccount', function ($q) use ($mapping) {
            $q->where('account_code', $mapping->account_code);
        })->firstOrFail();
    }
}
