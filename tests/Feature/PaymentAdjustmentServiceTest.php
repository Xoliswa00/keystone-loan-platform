<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\PaymentAdjustmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit-level coverage of PaymentAdjustmentService's own lifecycle logic —
 * ManualPaymentServiceTest/NuPayServiceTest already exercise this through
 * the real payment-posting flow (GL entries included); these tests target
 * the parts that flow doesn't fully cover on its own: cross-loan
 * aggregation and the disclosed-note roll-in at loan approval.
 */
class PaymentAdjustmentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = LoanProduct::firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Loan',
                'min_amount' => 500.00,
                'max_amount' => 3000.00,
                'min_months' => 1,
                'max_months' => 3,
                'monthly_interest_rate' => 0.0500,
                'initiation_fee_flat' => 150.00,
                'initiation_fee_rate' => 0.10,
                'initiation_fee_cap' => 1050.00,
                'monthly_service_fee' => 60.00,
                'vat_rate' => 0.15,
                'requires_enhanced_affordability' => false,
                'active' => true,
            ]
        );
    }

    private function makeStaff(): User
    {
        $user = User::create([
            'name' => 'Test Staff',
            'email' => 'adj-staff-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
        $user->forceFill(['system_role' => 'admin'])->save();

        return $user;
    }

    /** A customer with a loan, schedule, and one LoanRepayment to attach adjustments to. */
    private function makeCustomerWithLoan(float $currentBalance = 1000): array
    {
        $user = User::create([
            'name' => 'Test Client',
            'email' => 'adj-client-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_code' => 'CUST-'.uniqid('', true),
            'customer_type' => 'individual',
            'payment_terms' => 'debit_order',
            'credit_limit' => 5000,
            'current_balance' => $currentBalance,
        ]);

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 1000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'disbursed',
        ]);

        $loan = Loan::create([
            'loan_application_id' => $application->id,
            'user_id' => $user->id,
            'loan_type' => 'personal',
            'loan_amount' => 1000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'loan_term_months' => 1,
            'approved_amount' => 1000,
            'status' => 'disbursed',
            'remaining_balance' => $currentBalance,
        ]);

        $schedule = RepaymentSchedule::create([
            'loan_id' => $application->id,
            'user_id' => $user->id,
            'installment_number' => 1,
            'emi_amount' => 1050,
            'principal_amount' => 1000,
            'interest_amount' => 50,
            'fee_amount' => 0,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'repayment_schedule_id' => $schedule->id,
            'payment_amount' => 1050,
            'principal_amount' => 1000,
            'interest_amount' => 50,
            'fee_amount' => 0,
            'payment_date' => now()->toDateString(),
            'due_date' => $schedule->due_date,
            'status' => 'paid',
            'payment_method' => 'cash',
            'transaction_type' => 'manual',
        ]);

        return compact('user', 'customer', 'loan', 'schedule', 'repayment');
    }

    public function test_outstanding_shortfall_and_credit_sum_across_multiple_loans(): void
    {
        $staff = $this->makeStaff();
        ['customer' => $customer, 'loan' => $loanA, 'schedule' => $scheduleA, 'repayment' => $repaymentA] = $this->makeCustomerWithLoan();

        // A second, separate loan for the same customer.
        $applicationB = LoanApplication::create([
            'user_id' => $customer->user_id,
            'loan_product_id' => $this->product->id,
            'loan_type' => 'personal',
            'loan_term_months' => 1,
            'loan_amount' => 500,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'disbursed',
        ]);
        $loanB = Loan::create([
            'loan_application_id' => $applicationB->id,
            'user_id' => $customer->user_id,
            'loan_type' => 'personal',
            'loan_amount' => 500,
            'interest_rate' => 5,
            'loan_term' => 1,
            'loan_term_months' => 1,
            'approved_amount' => 500,
            'status' => 'disbursed',
            'remaining_balance' => 500,
        ]);
        $scheduleB = RepaymentSchedule::create([
            'loan_id' => $applicationB->id,
            'user_id' => $customer->user_id,
            'installment_number' => 1,
            'emi_amount' => 525,
            'principal_amount' => 500,
            'interest_amount' => 25,
            'fee_amount' => 0,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        $repaymentB = LoanRepayment::create([
            'loan_id' => $loanB->id,
            'user_id' => $customer->user_id,
            'repayment_schedule_id' => $scheduleB->id,
            'payment_amount' => 500,
            'principal_amount' => 500,
            'interest_amount' => 0,
            'fee_amount' => 0,
            'payment_date' => now()->toDateString(),
            'due_date' => $scheduleB->due_date,
            'status' => 'partial',
            'payment_method' => 'cash',
            'transaction_type' => 'manual',
        ]);

        $service = app(PaymentAdjustmentService::class);

        // Shortfall on loan A, credit on loan B — same customer.
        $service->recordShortfall($customer, $loanA, $scheduleA, $repaymentA, 30, $staff->id);
        $service->recordCredit($customer, $loanB, $scheduleB, $repaymentB, 15, $staff->id);

        $this->assertEquals(30, $service->outstandingShortfall($customer->fresh()));
        $this->assertEquals(15, $service->outstandingCredit($customer->fresh()));
    }

    public function test_consume_credit_draws_down_oldest_first_across_loans(): void
    {
        $staff = $this->makeStaff();
        ['customer' => $customer, 'loan' => $loan, 'schedule' => $schedule, 'repayment' => $repayment] = $this->makeCustomerWithLoan();

        $service = app(PaymentAdjustmentService::class);

        $older = $service->recordCredit($customer, $loan, $schedule, $repayment, 20, $staff->id);
        sleep(1);
        $service->recordCredit($customer, $loan, $schedule, $repayment, 50, $staff->id);

        $applied = $service->consumeCreditForSchedule($customer->fresh(), $schedule, 25);

        $this->assertEquals(25, $applied);
        $this->assertEquals(0, (float) $older->fresh()->outstanding_amount, 'The older credit must be drawn down first.');
        $this->assertEquals(45, $service->outstandingCredit($customer->fresh())); // 20 (fully consumed) + 50 - 5 consumed from it = 45 remaining
    }

    public function test_roll_shortfall_into_loan_discloses_without_touching_new_loan_terms(): void
    {
        $staff = $this->makeStaff();
        ['customer' => $customer, 'loan' => $oldLoan, 'schedule' => $schedule, 'repayment' => $repayment] = $this->makeCustomerWithLoan();

        $service = app(PaymentAdjustmentService::class);
        $shortfall = $service->recordShortfall($customer, $oldLoan, $schedule, $repayment, 75, $staff->id);

        $newLoan = Loan::create([
            'loan_application_id' => $oldLoan->loan_application_id,
            'user_id' => $customer->user_id,
            'loan_type' => 'personal',
            'loan_amount' => 2000,
            'interest_rate' => 5,
            'loan_term' => 1,
            'loan_term_months' => 1,
            'approved_amount' => 2000,
            'status' => 'approved',
            'remaining_balance' => 0,
        ]);

        $rolled = $service->rollShortfallIntoLoan($customer->fresh(), $newLoan, $staff->id);

        $this->assertEquals(75, $rolled);
        $this->assertEquals(75, (float) $newLoan->fresh()->carried_forward_shortfall);
        // The new loan's own terms are untouched — disclosed-note approach.
        $this->assertEquals(2000, (float) $newLoan->fresh()->approved_amount);
        $this->assertEquals(0, (float) $newLoan->fresh()->remaining_balance);

        $this->assertSame('rolled_into_loan', $shortfall->fresh()->status);
        $this->assertEquals($newLoan->id, $shortfall->fresh()->applied_to_loan_id);
        $this->assertEquals(0, $service->outstandingShortfall($customer->fresh()));
    }

    public function test_reverse_adjustment_voids_without_deleting(): void
    {
        $staff = $this->makeStaff();
        ['customer' => $customer, 'loan' => $loan, 'schedule' => $schedule, 'repayment' => $repayment] = $this->makeCustomerWithLoan();

        $service = app(PaymentAdjustmentService::class);
        $shortfall = $service->recordShortfall($customer, $loan, $schedule, $repayment, 40, $staff->id);

        $service->reverseAdjustmentFor($repayment);

        $this->assertDatabaseHas('payment_adjustments', [
            'id' => $shortfall->id,
            'status' => 'settled',
            'outstanding_amount' => 0,
        ]);
        $this->assertEquals(0, $service->outstandingShortfall($customer->fresh()));
    }
}
