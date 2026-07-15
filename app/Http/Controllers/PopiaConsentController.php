<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\PopiaConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class PopiaConsentController extends Controller
{
    /**
     * Record a grant or withdrawal for one consent type. Always inserts a
     * new row (see PopiaConsent) so consent history is never lost — this is
     * the data subject's own POPIA right to withdraw, exercised from their
     * profile page.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'consent_type' => ['required', 'in:data_processing,credit_bureau_check'],
            'granted' => ['required', 'boolean'],
        ]);

        // Withdrawal is blocked while an active loan exists — we still have
        // a live obligation to service that loan and to report to credit
        // bureaux under the NCA regardless of POPIA consent, and letting a
        // client silently withdraw here would desynchronise what the
        // profile page shows from what's actually still happening.
        if (! $validated['granted']
            && Loan::where('user_id', $request->user()->id)->active()->exists()) {
            return Redirect::route('profile.edit')->with('error',
                'You have an active loan — this consent is required to continue servicing it and to meet our credit-bureau reporting obligations under the National Credit Act. Contact us if you wish to settle your loan and withdraw consent.');
        }

        PopiaConsent::create([
            'user_id' => $request->user()->id,
            'consent_type' => $validated['consent_type'],
            'granted' => $validated['granted'],
            'ip_address' => $request->ip(),
            'context' => 'Profile settings',
            'consented_at' => now(),
        ]);

        return Redirect::route('profile.edit')->with('status', 'consent-updated');
    }
}
