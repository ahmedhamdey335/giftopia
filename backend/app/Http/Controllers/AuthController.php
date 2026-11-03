<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/** Create customer accounts and issue or revoke Sanctum API tokens. */
class AuthController extends Controller
{
    /** Register a customer; account roles are assigned by the server, not by the request. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'role' => UserRole::Customer->value,
        ]);

        return $this->tokenResponse($user, 201);
    }

    /** Verify credentials and issue a bearer token for later authenticated requests. */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error('The provided credentials are incorrect.', status: 401);
        }

        return $this->tokenResponse($user);
    }

    /** Revoke the token used for the current authenticated request. */
    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }

    /** Return the identity and role associated with the current token. */
    public function me(): JsonResponse
    {
        $user = request()->user();

        return ApiResponse::success([
            'id' => $user->id,
            'name' => strip_tags($user->name),
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => strip_tags($user->name),
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $user->createToken('api')->plainTextToken,
        ], 'Authenticated.', $status);
    }
}
