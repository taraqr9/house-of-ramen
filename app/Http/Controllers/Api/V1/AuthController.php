<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Sanctum personal-access-token auth for the POS app. Same credentials as
 * the web login (username + password); rate limited by the "api-login"
 * limiter (see AppServiceProvider).
 */
class AuthController extends ApiController
{
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('username', $request->input('username'))->first();

        // Same answer for "no such user" and "wrong password".
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid username or password.'], 401);
        }

        if ($user->isInactive()) {
            return response()->json(['success' => false, 'message' => 'Your account is disabled.'], 403);
        }

        if (is_null($user->password_changed_at) && ! is_null($user->password_setup_token)) {
            return response()->json(['success' => false, 'message' => 'You must change your temporary password on the web before using the app.'], 403);
        }

        $deviceName = $request->input('device_name');

        // One live token per device name.
        $user->tokens()->where('name', $deviceName)->delete();

        $expiresAt = now()->addDays(max(1, (int) config('sanctum.token_ttl_days', 30)));
        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return $this->ok([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => UserResource::make($user)->resolve($request),
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(UserResource::make($request->user()));
    }
}
