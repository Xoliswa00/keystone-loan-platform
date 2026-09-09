<?php

namespace Tests\Feature;

use App\Models\AccountSnapshot;
use App\Models\CreditReportDispute;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\Reporting\AccountSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The snapshot must be correct AS AT the reporting month, and must net
 * payments off oldest-first so a near-current partial payer does not report
 * as deeply delinquent.
 */
class AccountSnapshotServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): AccountSnapshotService
    {
        return app(AccountSnapshotService::class);
    }

    /**
     * @param  array<int, array{due:string, emi?:float, status?:string}>  $schedules
     */
    private function makeLoan(array $overrides = [], array $schedules = []): Loan
    {
        $uid = uniqid('', true);

        $user = User::create([
            'name' => 'Snap Client '.$uid,
            'email' => "snap-{$uid}@example.com",
            'password' => bcrypt('password'),
            'address' => '1 Test Street, Testville',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
        ]);

        Customer::create([
            'user_id' => $user->id,
            'customer_code' => 'CUST-'.$uid,
            'customer_type' => 'individual',
            'current_balance' => 0,
        ]);

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'loan_type' => 'personal',
            'loan_term_months' => max(1, count($schedules)),
            'loan_amount' => 3000,
            'purpose' => 'Personal',
            'terms_conditions' => true,
            'status' => 'disbursed',
            'nca_credit_type' => 'short_term',
        ]);

        $loan = Loan::create(array_merge([
            'loan_application_id' => $application->id,
            'user_id' => $user->id,
            'loan_type' => 'personal',
            'loan_amount' => 3000,
            'interest_rate' => 5,
            'loan_term' => max(1, count($schedules)),
            'loan_term_months' => max(1, count($schedules)),
            'approved_amount' => 3000,
            'status' => 'disbursed',
            'principal_amount' => 3000,
            'total_interest' => 150,
            'total_fees_excl_vat' => 180,
            'total_vat' => 27,
            'total_amount_due' => 3357,
            'remaining_balance' => 3357,
            'disbursed_date' => now()->subMonths(6)->toDateString(),
        ], $overrides));

        foreach ($schedules as $i => $row) {
            RepaymentSchedule::create([
                'loan_id' => $application->id,
                'user_id' => $user->id,
                'installment_number' => $i + 1,
                'emi_amount' => $row['emi'] ?? 1119,
                'principal_amount' => 1000,
                'interest_amount' => 50,
                'fee_amount' => 69,
                'due_date' => $row['due'],
                'status' => $row['status'] ?? 'pending',
            ]);
        }

        return $loan->fresh();
    }

    private function pay(Loan $loan, string $date, float $amount, ?int $scheduleId = null): void
    {
        $scheduleId ??= RepaymentSchedule::where('loan_id', $loan->loan_application_id)->orderBy('due_date')->value('id');

        DB::table('loan_repayments')->insert([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'repayment_schedule_id' => $scheduleId,
            'payment_amount' => $amount,
            'payment_date' => $date,
            'due_date' => $date,
            'status' => 'paid',
            'payment_method' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_builds_one_row_per_eligible_loan_with_stable_account_number(): void
    {
        $loan = $this->makeLoan([], [['due' => now()->subMonths(2)->toDateString()]]);
        $this->pay($loan, now()->subMonths(2)->toDateString(), 1119);

        $period = now()->subMonthNoOverflow()->format('Y-m');
        $result = $this->service()->buildForPeriod($period);

        $this->assertSame(1, $result['rows']);
        $snap = AccountSnapshot::where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame('KL-'.str_pad((string) $loan->id, 6, '0', STR_PAD_LEFT), $snap->account_no);
        $this->assertSame('Open', $snap->account_status);
        $this->assertEquals(0, (float) $snap->arrears_amount);
        $this->assertSame(0, $snap->days_past_due);
    }

    public function test_partial_payer_reports_small_arrears_not_full_delinquency(): void
    {
        // 3 instalments, all past due, each paid R1 short.
        $due = [
            now()->subMonths(3)->toDateString(),
            now()->subMonths(2)->toDateString(),
            now()->subMonths(1)->startOfMonth()->toDateString(),
        ];
        $loan = $this->makeLoan([], array_map(fn ($d) => ['due' => $d, 'emi' => 1000.0], $due));
        $schedules = RepaymentSchedule::where('loan_id', $loan->loan_application_id)->orderBy('due_date')->pluck('id')->all();

        $this->pay($loan, $due[0], 999, $schedules[0]);
        $this->pay($loan, $due[1], 999, $schedules[1]);
        $this->pay($loan, $due[2], 999, $schedules[2]);

        $this->service()->buildForPeriod(now()->subMonthNoOverflow()->format('Y-m'));
        $snap = AccountSnapshot::where('loan_id', $loan->id)->firstOrFail();

        // Total due 3000, paid 2997 => R3 arrears, and only the newest
        // instalment is uncovered (oldest-first allocation), NOT all three.
        $this->assertEqualsWithDelta(3.0, (float) $snap->arrears_amount, 0.01);
        $this->assertSame(1, $snap->instalments_in_arrears);
        $this->assertLessThan(45, $snap->days_past_due, 'DPD must be measured from the newest uncovered instalment, not the oldest.');
    }

    public function test_computation_is_as_at_period_end_not_now(): void
    {
        $dueDate = now()->subMonths(2)->startOfMonth()->toDateString();
        $loan = $this->makeLoan([], [['due' => $dueDate, 'emi' => 1000.0]]);

        // Payment lands the month AFTER the reporting period.
        $reportPeriod = Carbon::parse($dueDate)->format('Y-m');
        $this->pay($loan, Carbon::parse($dueDate)->addMonth()->toDateString(), 1000);

        $this->service()->buildForPeriod($reportPeriod);
        $snap = AccountSnapshot::latestGeneration($reportPeriod)->where('loan_id', $loan->id)->firstOrFail();

        $this->assertEqualsWithDelta(1000.0, (float) $snap->arrears_amount, 0.01,
            'A payment made after the reporting month must not reduce that month\'s arrears.');
        $this->assertGreaterThan(0, $snap->days_past_due);
    }

    public function test_reversed_loans_are_excluded(): void
    {
        $this->makeLoan(['status' => 'reversed'], [['due' => now()->subMonth()->toDateString()]]);

        $result = $this->service()->buildForPeriod(now()->subMonthNoOverflow()->format('Y-m'));

        $this->assertSame(0, $result['rows']);
    }

    public function test_written_off_loan_is_frozen_and_flagged(): void
    {
        $loan = $this->makeLoan([
            'status' => 'written_off',
            'written_off_date' => now()->subMonths(2)->toDateString(),
            'write_off_amount' => 2500,
            'remaining_balance' => 0,
        ], [['due' => now()->subMonths(4)->toDateString(), 'emi' => 1000.0]]);

        $this->service()->buildForPeriod(now()->subMonthNoOverflow()->format('Y-m'));
        $snap = AccountSnapshot::where('loan_id', $loan->id)->firstOrFail();

        $this->assertSame('WrittenOff', $snap->account_status);
        $this->assertEquals(2500, (float) $snap->write_off_amount);
        $this->assertEquals(0, (float) $snap->current_balance);
        $this->assertSame('W', substr($snap->payment_profile_24m, 0, 1));
    }

    public function test_rebuild_is_idempotent_and_drops_loans_that_left_the_book(): void
    {
        $keep = $this->makeLoan([], [['due' => now()->subMonths(2)->toDateString()]]);
        $this->pay($keep, now()->subMonths(2)->toDateString(), 1119);
        $drop = $this->makeLoan([], [['due' => now()->subMonths(2)->toDateString()]]);

        $period = now()->subMonthNoOverflow()->format('Y-m');
        $first = $this->service()->buildForPeriod($period);
        $this->assertSame(2, $first['rows']);

        // The second loan is reversed before the rebuild.
        $drop->update(['status' => 'reversed']);
        $second = $this->service()->buildForPeriod($period);

        $this->assertSame(1, $second['rows']);
        $this->assertSame($first['generation'], $second['generation'], 'Unconsumed generation is rebuilt in place.');
        $this->assertSame(1, AccountSnapshot::where('period', $period)->count());
        $this->assertNull(AccountSnapshot::where('loan_id', $drop->id)->first());
    }

    public function test_open_dispute_sets_under_dispute(): void
    {
        $loan = $this->makeLoan([], [['due' => now()->subMonths(2)->toDateString()]]);

        CreditReportDispute::create([
            'loan_id' => $loan->id,
            'raised_by' => $loan->user_id,
            'raised_at' => now()->subDays(3),
            'respond_by' => now()->addDays(17),
            'reason' => 'Balance disputed by consumer.',
            'reference' => 'DSP-001',
            'status' => 'open',
        ]);

        $this->service()->buildForPeriod(now()->subMonthNoOverflow()->format('Y-m'));
        $snap = AccountSnapshot::where('loan_id', $loan->id)->firstOrFail();

        $this->assertTrue((bool) $snap->under_dispute);
        $this->assertSame('DSP-001', $snap->dispute_reference);
    }

    public function test_rejects_a_malformed_period(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->buildForPeriod('2026-13');
    }
}
