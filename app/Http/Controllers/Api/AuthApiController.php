<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * Login — returns a Sanctum API token.
     * Rate limited to 3 attempts per minute.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials are incorrect.'],
            ]);
        }

        // Staff can use the API for the monitoring app — 2FA is enforced separately
        // Client-only fields like 'monitoring' scope differentiate staff vs client tokens
        $isStaff = $user->rule_id === 2
            || in_array($user->system_role ?? '', ['admin','finance','it_admin','loan_officer']);

        // Block blacklisted users
        if ($user->blacklisted) {
            return response()->json([
                'message' => 'Your account is not eligible for access. Please contact support.',
            ], 403);
        }

        // Revoke previous tokens for this device if requested
        if ($request->filled('device_name')) {
            $user->tokens()->where('name', $request->device_name)->delete();
        }

        $token = $user->createToken(
            $request->device_name ?? 'mobile_app',
            ['read', 'apply', 'profile']
        )->plainTextToken;

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $this->userPayload($user),
        ]);
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
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'customer_code'    => $user->customer?->customer_code,
            'status'           => $user->status,
            'profile_complete' => $user->customerProfile?->profile_complete ?? false,
        ];
    }
}
