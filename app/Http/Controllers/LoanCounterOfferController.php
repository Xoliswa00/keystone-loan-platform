<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\LoanCounterOffer;
use App\Models\LoanProduct;
use App\Services\AffordabilityService;
use App\Services\CloDecisionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Human-in-the-loop counter-offers. Originally built for applications that
 * failed the automated affordability check at submission
 * (LoanApplicationController::store(), status 'affordability_review'), and
 * now also available on any pending/under_review application — a reviewer
 * may want to offer a smaller amount for reasons the algorithm can't see
 * (risk, policy), not just automatic affordability failure. A staff reviewer
 * proposes an amount/term/product — bounded by what AffordabilityService
 * says the client can actually afford, never staff's unbounded judgment, to
 * avoid reckless-lending (NCA s.81) exposure — and the client explicitly
 * accepts or declines it before anything is finalised.
 */
class LoanCounterOfferController extends Controller
{
    /**
     * Statuses a counter-offer can be proposed on / responded to.
     */
    private const ELIGIBLE_STATUSES = ['pending', 'under_review', 'affordability_review'];

    public function __construct(
        protected AffordabilityService $affordability
    ) {}

    // ── Staff: propose a counter-offer ───────────────────────────────────────

    public function store(Request $request, LoanApplication $application)
    {
        abort_unless(in_array($application->status, self::ELIGIBLE_STATUSES, true), 404);

        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'months' => 'required|integer|min:1',
        ]);

        $product = LoanProduct::findOrFail($validated['loan_product_id']);

        if (! LoanProduct::availableFor($application->user)->whereKey($product->id)->exists()) {
            return back()->with('error', 'This loan product is not currently available for this client.');
        }

        $months = max($product->min_months, min((int) $validated['months'], $product->max_months));
        $amount = (float) $validated['amount'];

        // Re-derive the ceiling server-side rather than trusting the
        // suggestion the form was pre-filled with — the client's declared
        // affordability could have changed between page load and submit.
        $affordResult = $this->affordability->calculate($application->user);
        $maxAmount = $this->affordability->maxLoanAmount($product, $affordResult['max_instalment'], $months);

        if ($amount > $maxAmount) {
            return back()->withInput()->with('error',
                'That amount exceeds what this client can afford on this product/term '.
                '(max R'.number_format($maxAmount, 2).').');
        }

        $instalment = $product->calculateRepayment($amount, $months)['base_instalment'];

        // Superseding an earlier pending offer rather than stacking two live
        // ones — only one counter-offer should be actionable by the client
        // at a time.
        $application->counterOffers()->pending()->update(['status' => 'superseded']);

        LoanCounterOffer::create([
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'amount' => $amount,
            'months' => $months,
            'instalment' => $instalment,
            'proposed_by' => Auth::id(),
            'status' => 'pending',
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($application->user->email)->queue(
                new \App\Mail\LoanNotificationMail(
                    'A Revised Loan Offer Is Ready For You',
                    [
                        'We\'ve reviewed your application #'.str_pad($application->id, 6, '0', STR_PAD_LEFT).'.',
                        'Based on your affordability assessment, we can offer you R'.number_format($amount, 2).
                            ' over '.$months.' month'.($months > 1 ? 's' : '').' (R'.number_format($instalment, 2).'/month) on '.$product->name.'.',
                        'Log in to your account to accept or decline this offer.',
                    ],
                    $application
                )
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Counter-offer notification failed: '.$e->getMessage());
        }

        return back()->with('success', 'Counter-offer sent to the client for R'.number_format($amount, 2).'.');
    }

    // ── Client: accept the pending counter-offer ─────────────────────────────

    public function accept(LoanApplication $application, CloDecisionEngine $clo)
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless(in_array($application->status, self::ELIGIBLE_STATUSES, true), 404);

        $offer = $application->counterOffers()->pending()->latest()->first();
        abort_unless($offer, 404, 'No counter-offer is currently pending on this application.');

        // Re-check against a fresh affordability calculation before
        // finalizing — the offer may have been sitting unactioned for a
        // while, and store() only bounded it against the client's
        // affordability at proposal time. Accepting a now-unaffordable offer
        // verbatim would reopen the exact reckless-lending exposure (NCA
        // s.81) store()'s server-side ceiling exists to prevent.
        $affordResult = $this->affordability->calculate($application->user);
        $maxAmount = $this->affordability->maxLoanAmount($offer->product, $affordResult['max_instalment'], $offer->months);

        if ((float) $offer->amount > $maxAmount) {
            return back()->with('error',
                'This offer no longer fits your current affordability and can\'t be accepted as-is. '.
                'Please contact us so we can review a revised offer.');
        }

        // affordability_review has no fee/schedule yet (see
        // LoanApplicationController::store() — deliberately not created for
        // an amount nobody had agreed to). pending/under_review already has
        // both, from the original submission — those must be cleared before
        // regenerating for the new terms, or finalizeApplicationTerms() would
        // leave two live LoanFee rows for one application.
        $wasAffordabilityReview = $application->status === 'affordability_review';

        try {
            DB::transaction(function () use ($application, $offer, $clo, $wasAffordabilityReview) {
                // Re-fetch under a row lock — without it, a double-click (or
                // this accept() racing a concurrent decline()) could both
                // pass the "still pending" check above and both finalize
                // terms for the same application.
                $offer = LoanCounterOffer::lockForUpdate()->findOrFail($offer->id);

                if ($offer->status !== 'pending') {
                    throw new \RuntimeException('This offer has already been responded to.');
                }

                $product = $offer->product;
                $repayment = $product->calculateRepayment((float) $offer->amount, $offer->months);

                if (! $wasAffordabilityReview) {
                    $application->loanfee?->delete();
                    $application->repaymentSchedules()->delete();
                }

                $application->update([
                    'loan_product_id' => $product->id,
                    'loan_amount' => $offer->amount,
                    'loan_term_months' => $offer->months,
                    // affordability_review had no reviewer queue entry yet —
                    // this is what puts it there for the first time. pending/
                    // under_review is already correctly positioned; forcing it
                    // back to 'pending' would undo an in-progress reviewer
                    // assignment for no reason.
                    'status' => $wasAffordabilityReview ? 'pending' : $application->status,
                    'affordability_instalment_requested' => $repayment['base_instalment'],
                ]);

                $this->finalizeApplicationTermsFor($application, $product, (float) $offer->amount, $offer->months, $repayment);

                $offer->update(['status' => 'accepted', 'responded_at' => now()]);

                // Facts changed since the original evaluation — a fresh CLO
                // read against the accepted terms, same pattern as
                // AdminOpsController::reassess().
                $clo->evaluate($application->fresh());
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            \Illuminate\Support\Facades\Mail::to($application->user->email)->queue(
                new \App\Mail\LoanNotificationMail(
                    'Revised Application Under Review',
                    [
                        'Thanks for accepting the revised offer of R'.number_format($offer->amount, 2).'.',
                        'Your application is now under review — we\'ll notify you once a decision is made.',
                    ],
                    $application
                )
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Counter-offer acceptance notification failed: '.$e->getMessage());
        }

        return redirect()->route('loanapplications.show', $application->id)
            ->with('success', 'Offer accepted — your application is now under review.');
    }

    // ── Client: decline the pending counter-offer ────────────────────────────

    public function decline(LoanApplication $application)
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless(in_array($application->status, self::ELIGIBLE_STATUSES, true), 404);

        $offer = $application->counterOffers()->pending()->latest()->first();
        abort_unless($offer, 404, 'No counter-offer is currently pending on this application.');

        $offer->update(['status' => 'declined', 'responded_at' => now()]);

        if ($application->status === 'affordability_review') {
            // No viable original terms to fall back to — the client's
            // requested amount already failed affordability outright, and
            // this counter-offer was the only alternative on the table.
            $application->update([
                'status' => 'rejected',
                'approval_date' => now(),
                'reason' => 'Client declined counter-offer of R'.number_format($offer->amount, 2).
                    ' over '.$offer->months.' month(s).',
                'reviewer_id' => $offer->proposed_by,
            ]);

            $application->repaymentSchedules()->update(['status' => 'rejected']);

            return redirect()->route('loanapplications.show', $application->id)
                ->with('success', 'Offer declined.');
        }

        // pending/under_review: the client's original submission was already
        // affordable and valid — declining a counter-offer just means "keep
        // what I originally asked for," not "reject my whole application."
        // It stays exactly as it was; staff can still Approve/Reject it or
        // send a different counter-offer.
        return redirect()->route('loanapplications.show', $application->id)
            ->with('success', 'Offer declined — your original application is unchanged.');
    }

    /**
     * Thin wrapper so this controller doesn't depend on
     * LoanApplicationController directly for a single shared method —
     * delegates to the same finalizeApplicationTerms() logic used at
     * submission time (LoanFee snapshot + repayment schedule generation).
     */
    protected function finalizeApplicationTermsFor(LoanApplication $application, LoanProduct $product, float $amount, int $months, array $repayment): void
    {
        app(LoanApplicationController::class)->finalizeApplicationTerms($application, $product, $amount, $months, $repayment);
    }
}
