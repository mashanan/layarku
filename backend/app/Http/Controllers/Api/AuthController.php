<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Token auth with Sanctum: the app stores the plain-text token (DataStore)
 * and sends it as `Authorization: Bearer <token>`. One token per device,
 * so logging out on one phone does not log out the others.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // `role` is not mass assignable, so new accounts are always customers.
        $user = User::create($request->safe()->except('device_name'));

        return $this->tokenResponse($user, $request->validated('device_name'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Same error for "unknown email" and "wrong password", so the API
        // doesn't reveal which emails have accounts.
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw new ApiException('invalid_credentials', 'The email or password is incorrect.', 401);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    public function logout(Request $request): Response
    {
        // Revoke only the token used for this request (this device).
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'android')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], $status);
    }
}
