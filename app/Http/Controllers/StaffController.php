<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class StaffController extends Controller
{
    /**
     * Roles this page is allowed to assign. 'client' is deliberately excluded
     * from the invite form (new staff always start in a real staff role) but
     * included in the promote/demote select so a mistaken promotion can be
     * undone.
     */
    private const ASSIGNABLE_ROLES = ['loan_officer', 'finance', 'admin', 'it_admin', 'viewer'];

    public function index(Request $request)
    {
        $staff = User::whereNotNull('system_role')
            ->where('system_role', '!=', 'client')
            ->orderBy('name')
            ->get();

        $searchResults = collect();
        if ($request->filled('search')) {
            $searchResults = User::where('system_role', 'client')
                ->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%');
                })
                ->limit(10)
                ->get();
        }

        return view('admin.staff.index', [
            'staff' => $staff,
            'searchResults' => $searchResults,
            'search' => $request->search,
            'roles' => self::ASSIGNABLE_ROLES,
        ]);
    }

    /**
     * Invite a brand-new staff member — creates the account with a random
     * password and emails them Laravel's standard reset-password link so
     * they set their own credentials rather than us handling a plaintext one.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'system_role' => ['required', 'in:'.implode(',', self::ASSIGNABLE_ROLES)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => (string) str()->random(32),
            'address' => 'Head Office',
            'phone' => '0'.random_int(100000000, 999999999),
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
            'salary_payment_day' => 1,
            'ID_copy' => 'n/a',
            'email_verified_at' => now(),
        ]);

        $user->forceFill(['system_role' => $validated['system_role']])->save();

        try {
            Password::sendResetLink(['email' => $user->email]);
        } catch (\Exception $e) {
            Log::warning('Staff invite email failed: '.$e->getMessage());

            return back()->with('success', "Created {$user->name} as {$validated['system_role']}, but the invite email couldn't be sent — use \"Forgot password\" on the login page, or check mail configuration.");
        }

        return back()->with('success', "Invited {$user->name} as {$validated['system_role']} — they'll receive an email to set their password.");
    }

    /**
     * Promote an existing client to staff, change an existing staff member's
     * role, or demote staff back to 'client'.
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'system_role' => ['required', 'in:client,'.implode(',', self::ASSIGNABLE_ROLES)],
        ]);

        if ($user->id === $request->user()->id && $validated['system_role'] !== 'admin') {
            return back()->with('error', "You can't change your own role away from admin.");
        }

        $user->forceFill(['system_role' => $validated['system_role']])->save();

        return back()->with('success', "{$user->name}'s role is now {$validated['system_role']}.");
    }
}
