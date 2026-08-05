<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Mail\InvestorOtpMail;
use App\Models\AuditLog;
use App\Models\FundingFacility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Passwordless investor login: ID/registration number + email look up a
 * FundingFacility, then a one-time email code confirms it's really them.
 * Deliberately not a Laravel Auth guard — investors aren't User records,
 * this is a lightweight session flag scoped to app/Http/Middleware/
 * EnsureInvestorAuthenticated.
 */
class InvestorAuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function showLogin()
    {
        return view('investor.login');
    }

    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'id_number' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $idNumber = trim($validated['id_number']);
        $email = strtolower(trim($validated['email']));

        $startedAt = microtime(true);
        $facility = $this->findFacility($idNumber, $email);

        // Always the same response whether or not a match was found — this
        // endpoint must never be usable to enumerate valid investor ID
        // number/email combinations.
        if ($facility) {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->cacheKey($idNumber, $email), ['code' => $code, 'attempts' => 0], now()->addMinutes(self::OTP_TTL_MINUTES));

            try {
                Mail::to($email)->send(new InvestorOtpMail($code));
                AuditLog::record('investor_otp_sent', $facility, [], ['email' => $email]);
            } catch (\Exception $e) {
                // Never let a mail failure surface differently than the "no
                // match" path below — that difference would itself be an
                // enumeration side-channel (matched IDs 500, unmatched ones
                // redirect cleanly).
                \Illuminate\Support\Facades\Log::error('Failed to send investor OTP to '.$email.': '.$e->getMessage());
            }
        }

        // Response-timing normalisation: InvestorOtpMail implements
        // ShouldQueue, which removes the SMTP round-trip from this request
        // once QUEUE_CONNECTION is a real async driver — but under 'sync'
        // (this app's current config) a queued dispatch still runs inline,
        // so the matched path is measurably slower than the unmatched one
        // purely from the mail send. Pad the fast path up to a floor so
        // response latency alone can't distinguish a match from a non-match.
        $elapsedMs = (microtime(true) - $startedAt) * 1000;
        $floorMs = 400;
        if ($elapsedMs < $floorMs) {
            usleep((int) (($floorMs - $elapsedMs) * 1000));
        }

        session(['investor_pending_id_number' => $idNumber, 'investor_pending_email' => $email]);

        return redirect()->route('investor.verify')
            ->with('info', "If those details match our records, we've sent a 6-digit code to that email address.");
    }

    public function showVerify()
    {
        if (! session('investor_pending_email')) {
            return redirect()->route('investor.login');
        }

        return view('investor.verify');
    }

    public function verifyCode(Request $request)
    {
        $idNumber = session('investor_pending_id_number');
        $email = session('investor_pending_email');

        if (! $idNumber || ! $email) {
            return redirect()->route('investor.login');
        }

        $request->validate(['code' => ['required', 'string']]);

        $key = $this->cacheKey($idNumber, $email);
        $entry = Cache::get($key);

        if (! $entry || $entry['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($key);
            session()->forget(['investor_pending_id_number', 'investor_pending_email']);

            return redirect()->route('investor.login')->with('error', 'That code has expired. Please start again.');
        }

        if (! hash_equals($entry['code'], trim($request->code))) {
            $entry['attempts']++;
            Cache::put($key, $entry, now()->addMinutes(self::OTP_TTL_MINUTES));

            return back()->with('error', 'Incorrect code. Please try again.');
        }

        Cache::forget($key);
        session()->forget(['investor_pending_id_number', 'investor_pending_email']);
        session([
            'investor_authenticated' => true,
            'investor_id_number' => $idNumber,
            'investor_email' => $email,
        ]);

        $facility = $this->findFacility($idNumber, $email);
        if ($facility) {
            AuditLog::record('investor_login_success', $facility, [], ['email' => $email]);
        }

        return redirect()->route('investor.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['investor_authenticated', 'investor_id_number', 'investor_email']);

        return redirect()->route('investor.login')->with('success', 'Logged out.');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function findFacility(string $idNumber, string $email): ?FundingFacility
    {
        return FundingFacility::whereRaw('LOWER(funder_id_or_reg) = ?', [strtolower($idNumber)])
            ->whereRaw('LOWER(contact_email) = ?', [strtolower($email)])
            ->first();
    }

    private function cacheKey(string $idNumber, string $email): string
    {
        return 'investor_otp_'.hash('sha256', strtolower($idNumber).'|'.strtolower($email));
    }
}
