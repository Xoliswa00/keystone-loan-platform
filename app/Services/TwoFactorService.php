<?php

namespace App\Services;

use App\Mail\LoanNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Shared OTP send/verify logic for admin/staff 2FA — used by the web login
 * flow (TwoFactorController) and the API login flow (AuthApiController) so
 * both enforce identical rules against the same two_factor_codes table.
 */
class TwoFactorService
{
    const CODE_TTL_MINUTES = 10;

    const MAX_ATTEMPTS = 3;

    public function sendCode(int $userId, ?string $ip = null): void
    {
        // Expire old codes
        DB::table('two_factor_codes')
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('two_factor_codes')->insert([
            'user_id' => $userId,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'ip_address' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($userId);
        if ($user) {
            try {
                Mail::to($user->email)->send(new LoanNotificationMail(
                    'Your Keystone Login Verification Code',
                    [
                        "Dear {$user->name},",
                        "Your verification code is: {$code}",
                        'This code expires in '.self::CODE_TTL_MINUTES.' minutes.',
                        'If you did not request this, please contact IT immediately.',
                    ],
                    null
                ));
            } catch (\Exception $e) {
                Log::error("2FA email failed for user #{$userId}: ".$e->getMessage());
            }
        }
    }

    /**
     * Verify a code against the two_factor_codes table. Marks the code used
     * and stamps two_factor_verified_at on success. Does not touch session
     * state or attempt-throttling — callers own that (web uses the session,
     * API should throttle by route middleware).
     */
    public function verifyCode(int $userId, string $code): bool
    {
        $record = DB::table('two_factor_codes')
            ->where('user_id', $userId)
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return false;
        }

        DB::table('two_factor_codes')->where('id', $record->id)->update(['used_at' => now()]);

        User::where('id', $userId)->update(['two_factor_verified_at' => now()]);

        return true;
    }

    public function isStaffRole(User $user): bool
    {
        $role = $user->system_role ?? ($user->rule_id === 2 ? 'admin' : 'client');

        return in_array($role, ['admin', 'finance', 'it_admin', 'loan_officer']);
    }
}
