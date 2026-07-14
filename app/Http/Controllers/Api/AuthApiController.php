<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(protected TwoFactorService $twoFactor) {}

    /**
     * Login — returns a Sanctum API token, or (for staff with 2FA enabled)
     * triggers an OTP email and asks the client to call two-factor/verify.
     * Rate limited to 3 attempts per minute.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials are incorrect.'],
            ]);
        }

        // Block blacklisted users
        if ($user->blacklisted) {
            return response()->json([
                'message' => 'Your account is not eligible for access. Please contact support.',
            ], 403);
        }

        if ($this->twoFactor->isStaffRole($user) && $user->two_factor_enabled) {
            $this->twoFactor->sendCode($user->id, $request->ip());

            return response()->json([
                'requires_2fa' => true,
                'user_id' => $user->id,
                'message' => 'A verification code has been sent to your email.',
            ], 202);
        }

        return response()->json($this->issueToken($user, $request->device_name));
    }

    /**
     * Complete a staff login by verifying the OTP code sent during login().
     * Rate limited via routes/api.php (throttle:login).
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string|size:6',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::findOrFail($request->user_id);

        if (! $this->twoFactor->isStaffRole($user) || ! $user->two_factor_enabled) {
            throw ValidationException::withMessages([
                'user_id' => ['Two-factor verification is not required for this account.'],
            ]);
        }

        if (! $this->twoFactor->verifyCode($user->id, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        return response()->json($this->issueToken($user, $request->device_name));
    }

    private function issueToken(User $user, ?string $deviceName): array
    {
        // Revoke previous tokens for this device if requested
        if ($deviceName) {
            $user->tokens()->where('name', $deviceName)->delete();
        }

        $token = $user->createToken(
            $deviceName ?? 'mobile_app',
            ['read', 'apply', 'profile']
        )->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Current authenticated user.
     */
    public function user(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'customer_code' => $user->customer?->customer_code,
            'status' => $user->status,
            'profile_complete' => $user->customerProfile?->profile_complete ?? false,
        ];
    }
}
