<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\AffordabilityService;
use App\Services\CustomerLimitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    protected AffordabilityService $affordability;

    protected CustomerLimitationService $limitations;

    public function __construct(
        AffordabilityService $affordability,
        CustomerLimitationService $limitations
    ) {
        $this->affordability = $affordability;
        $this->limitations = $limitations;
    }

    public function index(Request $request)
    {
        $activeLoanStatuses = ['settled', 'rejected', 'archived', 'written_off'];

        $query = Customer::query()
            ->leftJoin('users', 'customers.user_id', '=', 'users.id')
            ->select(
                'customers.*',
                'users.name', 'users.email', 'users.phone', 'users.ID_Number',
                'users.status as user_status', 'users.blacklisted',
                'users.last_rejected_at', 'users.applications_restricted', 'users.system_role'
            )
            ->selectSub(function ($q) use ($activeLoanStatuses) {
                $q->selectRaw('COUNT(*)')->from('loans')
                    ->whereColumn('loans.user_id', 'customers.user_id')
                    ->whereNotIn('loans.status', $activeLoanStatuses);
            }, 'active_loans_count')
            ->selectSub(function ($q) use ($activeLoanStatuses) {
                $q->selectRaw('COALESCE(SUM(remaining_balance), 0)')->from('loans')
                    ->whereColumn('loans.user_id', 'customers.user_id')
                    ->whereNotIn('loans.status', $activeLoanStatuses);
            }, 'total_outstanding')
            ->orderBy('customers.id', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customers.customer_code', 'like', "%$search%")
                    ->orWhere('users.name', 'like', "%$search%")
                    ->orWhere('users.ID_Number', 'like', "%$search%")
                    ->orWhere('users.phone', 'like', "%$search%")
                    ->orWhere('users.email', 'like', "%$search%");
            });
        }

        if ($request->input('filter') === 'overdue') {
            $query->where('customers.current_balance', '>', 0);
        } elseif ($request->input('filter') === 'restricted') {
            $query->where(fn ($q) => $q->where('users.applications_restricted', true)->orWhere('users.blacklisted', true));
        } elseif ($request->input('filter') === 'active') {
            $query->having('active_loans_count', '>', 0);
        }

        $customers = $query->paginate(20);

        $stats = [
            'total' => Customer::count(),
            'active' => DB::table('loans')->whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])->distinct('user_id')->count('user_id'),
            'overdue' => DB::table('customers')->where('current_balance', '>', 0)->count(),
            'restricted' => User::where('applications_restricted', true)->orWhere('blacklisted', true)->count(),
        ];

        return view('customer.index', compact('customers', 'stats'));
    }

    public function show(Customer $customer)
    {
        $user = User::with(['customerProfile', 'customerDocuments', 'loanApplications.loanfee'])
            ->findOrFail($customer->user_id);

        $loans = \App\Models\Loan::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')->get();

        $repayments = \App\Models\LoanRepayment::where('user_id', $user->id)
            ->orderBy('payment_date', 'desc')->take(20)->get();

        $notes = DB::table('admin_notes')
            ->join('users as au', 'au.id', '=', 'admin_notes.user_id')
            ->join('loan_applications as la', 'la.id', '=', 'admin_notes.loan_application_id')
            ->where('la.user_id', $user->id)
            ->select('admin_notes.*', 'au.name as admin_name')
            ->orderByDesc('admin_notes.created_at')->take(20)->get();

        $affordability = $this->affordability->calculate($user);
        $profileStatus = $this->affordability->profileStatus($user);
        $limitCheck = $this->limitations->canApply($user);
        $recoveryCase = \App\Models\DebtRecovery::where('user_id', $user->id)
            ->whereNotIn('status', ['recovered', 'closed'])->first();

        return view('customer.show', compact(
            'customer', 'user', 'loans', 'repayments', 'notes',
            'affordability', 'profileStatus', 'limitCheck', 'recoveryCase'
        ));
    }

    public function restrict(Request $request, Customer $customer)
    {
        $request->validate(['reason' => 'required|string|max:500', 'expires_at' => 'nullable|date|after:today']);
        $user = $customer->user;
        $this->limitations->restrict($user, $request->reason,
            $request->expires_at ? \Carbon\Carbon::parse($request->expires_at) : null, Auth::id());
        \App\Models\AuditLog::record('restricted', $user, [], ['reason' => $request->reason]);

        return back()->with('success', "{$user->name}'s account restricted.");
    }

    public function lift(Customer $customer)
    {
        $user = $customer->user;
        $this->limitations->lift($user);
        \App\Models\AuditLog::record('restriction_lifted', $user, [], []);

        return back()->with('success', "Restriction lifted for {$user->name}.");
    }

    public function toggleBlacklist(Customer $customer)
    {
        $user = $customer->user;
        $new = ! $user->blacklisted;
        // forceFill(), not update() — blacklisted/blacklisted_at aren't in
        // User::$fillable, so update() silently no-ops and this button has
        // never actually persisted a blacklist.
        $user->forceFill(['blacklisted' => $new, 'blacklisted_at' => $new ? now() : null])->save();
        \App\Models\AuditLog::record($new ? 'blacklisted' : 'blacklist_lifted', $user, [], []);

        return back()->with('success', $user->name.($new ? ' blacklisted.' : ' removed from blacklist.'));
    }

    /**
     * Per-client override — grants this client access to extended-term
     * (>1 month) loan products even while those products stay inactive for
     * the general client base. See LoanProduct::scopeAvailableFor().
     */
    public function toggleExtendedTerms(Customer $customer)
    {
        $user = $customer->user;
        $new = ! $user->extended_terms_eligible;
        // forceFill(), not update() — these columns are deliberately kept out
        // of User::$fillable (same reason StaffController::updateRole() uses
        // forceFill() for system_role), so a plain update() call here would
        // silently no-op instead of persisting.
        $user->forceFill([
            'extended_terms_eligible' => $new,
            'extended_terms_granted_at' => $new ? now() : null,
            'extended_terms_granted_by' => $new ? Auth::id() : null,
        ])->save();
        \App\Models\AuditLog::record($new ? 'extended_terms_granted' : 'extended_terms_revoked', $user, [], []);

        return back()->with('success', $user->name.($new ? ' can now apply for extended-term loans.' : "'s extended-term eligibility revoked."));
    }

    // Stubs
    public function create() {}

    public function store(StoreCustomerRequest $request) {}

    public function edit(Customer $customer) {}

    public function update(UpdateCustomerRequest $request, Customer $customer) {}

    public function destroy(Customer $customer) {}
}
