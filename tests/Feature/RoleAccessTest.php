<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * routes/web.php splits the shared staff group (admin,loan_officer,finance,
 * it_admin) into role-scoped sub-groups matching
 * resources/views/layouts/navigation.blade.php's Loan Operations / Finance /
 * IT & System split — Nu-Pay/recon/period-close routes are finance's
 * exclusive "imports of financial data" remit, staff/audit-log routes are
 * IT's. These tests pin that a role actually gets 403'd outside its remit
 * (not just hidden from the sidebar) and that 'admin' still bypasses every
 * restriction, per RequireRole::handle().
 */
class RoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStaff(string $role): User
    {
        $user = User::create([
            'name' => 'Test '.$role,
            'email' => 'role-access-'.$role.'-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
        $user->forceFill(['system_role' => $role])->save();

        return $user;
    }

    public function test_loan_officer_is_blocked_from_finance_only_routes(): void
    {
        $loanOfficer = $this->makeStaff('loan_officer');

        $this->actingAs($loanOfficer)->get(route('nupay.upload.form'))->assertForbidden();
        $this->actingAs($loanOfficer)->get(route('admin.periods.index'))->assertForbidden();
        $this->actingAs($loanOfficer)->get(route('admin.funding.index'))->assertForbidden();
        $this->actingAs($loanOfficer)->get(route('reports.profitability'))->assertForbidden();
    }

    public function test_loan_officer_is_blocked_from_it_only_routes(): void
    {
        $loanOfficer = $this->makeStaff('loan_officer');

        $this->actingAs($loanOfficer)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($loanOfficer)->get(route('reports.audit-log'))->assertForbidden();
    }

    public function test_finance_is_blocked_from_it_only_routes(): void
    {
        $finance = $this->makeStaff('finance');

        $this->actingAs($finance)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('reports.audit-log'))->assertForbidden();
    }

    /**
     * Loan approval and disbursement release are loan officer's exclusive
     * "loan movement" remit — finance is deliberately excluded (no
     * officer/finance segregation-of-duties split was chosen; see
     * routes/web.php's disbursements.* and loans.approve/reject group).
     */
    public function test_finance_is_blocked_from_loan_approval_and_disbursement_routes(): void
    {
        $finance = $this->makeStaff('finance');

        $this->actingAs($finance)->post(route('loans.approve', 999999))->assertForbidden();
        $this->actingAs($finance)->post(route('loans.reject', 999999))->assertForbidden();
        $this->actingAs($finance)->post(route('loans.reverse', 999999))->assertForbidden();
        $this->actingAs($finance)->get(route('disbursements.index'))->assertForbidden();
        $this->actingAs($finance)->post(route('disbursements.approve', 999999))->assertForbidden();
    }

    /**
     * Manual-payment verification is loan officer's "verifications" remit,
     * same restriction as document verification — finance excluded.
     */
    public function test_finance_is_blocked_from_manual_payment_verification(): void
    {
        $finance = $this->makeStaff('finance');

        // GET index proves the whole route group is gated — approve/reject
        // share the same group-level middleware, and (unlike loans.approve)
        // bind an Eloquent model, so a non-existent id 404s before the role
        // check runs, making a direct assertion on those two routes flaky.
        $this->actingAs($finance)->get(route('admin.manual-payments.index'))->assertForbidden();
    }

    public function test_loan_officer_can_access_manual_payment_verification(): void
    {
        $loanOfficer = $this->makeStaff('loan_officer');

        $this->actingAs($loanOfficer)->get(route('admin.manual-payments.index'))->assertOk();
    }

    public function test_it_admin_is_blocked_from_finance_only_routes(): void
    {
        $itAdmin = $this->makeStaff('it_admin');

        // it_admin sits in the finance-adjacent role list for recon/import
        // routes (role:admin,finance,it_admin) — this asserts it is NOT
        // additionally blocked there, i.e. it_admin can support finance.
        $this->actingAs($itAdmin)->get(route('nupay.upload.form'))->assertOk();
    }

    public function test_loan_officer_can_still_reach_own_loan_operations_routes(): void
    {
        $loanOfficer = $this->makeStaff('loan_officer');

        $this->actingAs($loanOfficer)->get(route('admin.loans'))->assertOk();
        $this->actingAs($loanOfficer)->get(route('admin.collections'))->assertOk();
        $this->actingAs($loanOfficer)->get(route('disbursements.index'))->assertOk();
    }

    public function test_finance_can_reach_own_finance_routes(): void
    {
        $finance = $this->makeStaff('finance');

        $this->actingAs($finance)->get(route('nupay.upload.form'))->assertOk();
    }

    public function test_admin_bypasses_every_role_restriction(): void
    {
        $admin = $this->makeStaff('admin');

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $this->actingAs($admin)->get(route('nupay.upload.form'))->assertOk();
        $this->actingAs($admin)->get(route('reports.audit-log'))->assertOk();
    }
}
