<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Redirect;

use Illuminate\View\View;
use Illuminate\Support\Facades\Log; // Import the Log facade



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
            'address' => ['nullable', 'string', 'max:700'],
            'ID_Number' => ['required', 'string', 'max:20', 'unique:users,ID_Number'],

            'employment_status' => ['nullable','required', 'in:Full-time,Part-time,Self-employed,Unemployed'],
            'salary_frequency' => ['nullable', 'required','in:Weekly,Bi-weekly,Monthly'],
            'net_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_payment_day' => ['nullable','required' ,'integer', 'between:1,31'],
            'credit_score' => ['nullable', 'numeric', 'min:0'],
            'ID_copy' => ['required', 'file'], // Accepting file uploads for ID copy
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
            'employment_status' => $request->employment_status,
            'salary_frequency' => $request->salary_frequency,
            'net_salary' => $request->net_salary,
            'salary_payment_day' => $request->salary_payment_day,
            'credit_score' => $request->credit_score,
            'ID_copy' => $filePath,
        ]);

        // Fire the Registered event
        event(new Registered($user));

        

        // Redirect to home
        return  Redirect::route('loanapplications.create')->with('success', 'Registration successful.');
    }
    
    
    
}
