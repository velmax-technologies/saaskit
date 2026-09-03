<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\CreateAccessToken;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\ResetUserPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\Auth\SessionResource;
use App\Http\Resources\Api\Auth\UserResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUser $registerUser,
        CreateAccessToken $createAccessToken,
    ): JsonResponse {
        $user = $registerUser->execute(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        $user->sendEmailVerificationNotification();

        $token = $createAccessToken->execute(
            user: $user,
            name: 'api',
            abilities: ['*'],
        )->plainTextToken;

        return ApiResponse::created(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Account created successfully.',
        );
    }

    public function login(
        LoginRequest $request,
        LoginUser $loginUser,
        CreateAccessToken $createAccessToken,
    ): JsonResponse {
        $user = $loginUser->execute(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        $token = $createAccessToken->execute(
            user: $user,
            name: 'api',
            abilities: ['*'],
        )->plainTextToken;

        return ApiResponse::success(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Login successful.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'user' => new UserResource($request->user()),
            ],
            message: 'Authenticated user retrieved successfully.',
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = $request->user()
            ->tokens()
            ->latest()
            ->get();

        return ApiResponse::success(
            data: [
                'sessions' => SessionResource::collection($sessions),
            ],
            message: 'Active sessions retrieved successfully.',
        );
    }

    public function revokeSession(
        Request $request,
        string $publicId,
    ): JsonResponse {
        $token = $request->user()
            ->tokens()
            ->where('public_id', $publicId)
            ->first();

        if (! $token) {
            return ApiResponse::notFound('Session not found.');
        }

        $token->delete();

        return ApiResponse::success(
            message: 'Session revoked successfully.',
        );
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();

        $query = $request->user()
            ->tokens();

        if ($currentToken) {
            $query->where('id', '!=', $currentToken->id);
        }

        $query->delete();

        return ApiResponse::success(
            message: 'Other sessions revoked successfully.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(
            message: 'Logged out successfully.',
        );
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        SendPasswordResetLink $sendPasswordResetLink,
    ): JsonResponse {
        $sendPasswordResetLink->execute(
            email: $request->string('email')->toString(),
        );

        return ApiResponse::success(
            message: 'If an account exists for that email address, a password reset link has been sent.',
        );
    }

    public function resetPassword(
        ResetPasswordRequest $request,
        ResetUserPassword $resetUserPassword,
    ): JsonResponse {
        $reset = $resetUserPassword->execute(
            email: $request->string('email')->toString(),
            token: $request->string('token')->toString(),
            password: $request->string('password')->toString(),
        );

        if (! $reset) {
            return ApiResponse::error(
                message: 'The password reset token is invalid or has expired.',
                status: 422,
            );
        }

        return ApiResponse::success(
            message: 'Password reset successfully. Please log in again.',
        );
    }
}
