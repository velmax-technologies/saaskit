<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\Api\ApiAbility;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);

        $token = $user->createToken('api', [ApiAbility::PROFILE_READ])->plainTextToken;

        return ApiResponse::success(
            'Registration successful.',
            [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            201,
        );
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api', [ApiAbility::PROFILE_READ])->plainTextToken;

        return ApiResponse::success(
            'Login successful.',
            [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success('Logout successful.');
    }

    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->latest('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ])
            ->values();

        return ApiResponse::success(
            'API tokens retrieved successfully.',
            [
                'tokens' => $tokens,
            ],
        );
    }

    public function revokeToken(Request $request, int $token): JsonResponse
    {
        $apiToken = $request->user()
            ->tokens()
            ->findOrFail($token);

        $apiToken->delete();

        return ApiResponse::success('API token revoked successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Authenticated user retrieved successfully.',
            [
                'user' => new UserResource($request->user()),
            ],
        );
    }
}
