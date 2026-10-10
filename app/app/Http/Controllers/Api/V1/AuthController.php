<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApiTokenResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\Api\ApiAbility;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
        $user->sendEmailVerificationNotification();

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

        $rateLimitKey = 'auth-login:'.hash(
            'sha256',
            strtolower(trim($validated['email'])).'|'.$request->ip(),
        );

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.',
                'errors' => null,
            ], 429, [
                'Retry-After' => (string) max(1, RateLimiter::availableIn($rateLimitKey)),
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);

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

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return ApiResponse::success(
            $user->hasVerifiedEmail()
                ? 'Email address is already verified.'
                : 'Email verification link sent.'
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
            ->get();

        return ApiResponse::success(
            'API tokens retrieved successfully.',
            [
                'tokens' => ApiTokenResource::collection($tokens),
            ],
        );
    }

    public function revokeToken(Request $request, string $token): JsonResponse
    {
        $apiToken = $request->user()
            ->tokens()
            ->where('public_id', $token)
            ->firstOrFail();

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
