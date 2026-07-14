<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TwoFactorController extends Controller
{
    const CODE_TTL_MINUTES = TwoFactorService::CODE_TTL_MINUTES;

    const MAX_ATTEMPTS = TwoFactorService::MAX_ATTEMPTS;

    public function __construct(protected TwoFactorService $twoFactor) {}

    /**
     * Show the OTP verification form.
     * Sends a new code automatically on page load.
     */
    public function show(Request $request)
    {
        if (! session('2fa_user_id')) {
            return redirect()->route('login');
        }

        // Auto-send code if none sent yet in this session
        if (! session('2fa_code_sent')) {
            $this->sendCode($request);
        }

        return view('auth.two-factor', [
            'email' => $this->maskedEmail(session('2fa_user_id')),
        ]);
    }

    /**
     * Send / resend the OTP code.
     */
    public function send(Request $request)
    {
        $this->sendCode($request);

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * Verify the OTP code.
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $userId = session('2fa_user_id');

        if (! $userId) {
            return redirect()->route('login')
                ->with('error', 'Session expired. Please sign in again.');
        }

        // Throttle: max 3 wrong attempts
        $attemptKey = "2fa_attempts_{$userId}";
        $attempts = session($attemptKey, 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Auth::logout();
            $request->session()->flush();

            return redirect()->route('login')
                ->with('error', 'Too many incorrect attempts. Please sign in again.');
        }

        if (! $this->twoFactor->verifyCode($userId, $request->code)) {
            session([$attemptKey => $attempts + 1]);

            return back()->with('error', 'Invalid or expired code. '.
                (self::MAX_ATTEMPTS - $attempts - 1).' attempt(s) remaining.');
        }

        // Complete the login
        $user = User::findOrFail($userId);
        Auth::login($user);

        $request->session()->forget(['2fa_user_id', '2fa_code_sent', $attemptKey]);
        $request->session()->regenerate();

        Log::info("2FA verified for admin user #{$user->id} from IP ".$request->ip());

        return redirect()->intended(route('admin.dashboard'));
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function sendCode(Request $request): void
    {
        $userId = session('2fa_user_id');
        if (! $userId) {
            return;
        }

        $this->twoFactor->sendCode($userId, $request->ip());
        session(['2fa_code_sent' => true]);
    }

    protected function maskedEmail(int $userId): string
    {
        $email = User::find($userId)?->email ?? '';
        [$local, $domain] = explode('@', $email) + ['', ''];
        $masked = substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 4)).substr($local, -2);

        return $masked.'@'.$domain;
    }
}
