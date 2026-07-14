<?php

namespace App\Http\Controllers;

use App\Mail\LoanNotificationMail;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $company = Company::settings();
        $to = $company?->support_email ?? $company?->email ?? config('mail.from.address');

        try {
            Mail::to($to)->queue(new LoanNotificationMail(
                'New Website Query from '.$validated['name'],
                [
                    'Name: '.$validated['name'],
                    'Email: '.$validated['email'],
                    'Phone: '.($validated['phone'] ?? 'Not provided'),
                    'Message:',
                    $validated['message'],
                ],
                null
            ));
        } catch (\Exception $e) {
            Log::warning('Contact form email failed: '.$e->getMessage());

            return back()->withInput()->with('error', 'Something went wrong sending your message. Please try WhatsApp or email us directly.');
        }

        return back()->with('success', "Thanks, {$validated['name']} — we've received your message and will be in touch shortly.");
    }
}
