<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PopiaConsent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules;
use Illuminate\View\View; // Import the Log facade

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Validate the request data
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required', 'string', 'regex:/^(\+?\d{1,3}[- ]?)?\d{10}$/', 'unique:users,phone'],
            'address' => ['required', 'string', 'max:700'],
            'ID_Number' => ['required', 'string', 'max:20', 'unique:users,ID_Number'],

            // Employment/income/credit-score used to be collected here too, but
            // customer_profiles (built via CustomerProfileController after signup)
            // is the actual source of truth AffordabilityService/CloDecisionEngine
            // read from — asking for it again at registration just invited drift.
            'salary_payment_day' => ['nullable', 'required', 'integer', 'between:1,31'],
            'ID_copy' => ['required', 'file'], // Accepting file uploads for ID copy
            'terms' => ['accepted'], // Terms & Conditions acknowledgement only
            'popia_consent' => ['accepted'], // NCA credit-check + POPIA data-processing consent — distinct from 'terms' so it's a specific, informed consent rather than inferred from agreeing to the site's general terms
        ]);

        try {
            // Handle file upload for ID_copy
            $filePath = $request->hasFile('ID_copy')
                ? $request->file('ID_copy')->store('id_copies', 'public')
                : null;

        } catch (\Exception $e) {
            // Log the error for debugging

            // Handle errors
            return redirect()->back()->withErrors(['error' => 'Failed to register user. Please try again.']);
        }
        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'ID_Number' => $request->ID_Number,
            'salary_payment_day' => $request->salary_payment_day,
            'ID_copy' => $filePath,
        ]);

        // The "popia_consent" checkbox covers both consent types recorded
        // below — record each distinctly since CloDecisionEngine gates
        // specifically on 'data_processing', and NCA credit checks are a
        // separate consent type even though they're granted together here.
        foreach (['data_processing', 'credit_bureau_check'] as $consentType) {
            PopiaConsent::create([
                'user_id' => $user->id,
                'consent_type' => $consentType,
                'granted' => true,
                'ip_address' => $request->ip(),
                'context' => 'Registration form',
                'consented_at' => now(),
            ]);
        }

        // Fire the Registered event
        event(new Registered($user));

        Auth::login($user);

        // Redirect to home
        return Redirect::route('loanapplications.create')->with('success', 'Registration successful.');
    }
}
