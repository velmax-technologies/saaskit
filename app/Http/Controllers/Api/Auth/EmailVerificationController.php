<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $publicId, string $hash): JsonResponse
    {
        $user = User::where('public_id', $publicId)->firstOrFail();

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error(
                message: 'The email verification link is invalid.',
                status: 403,
            );
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(
                message: 'Email address has already been verified.',
            );
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return ApiResponse::success(
            message: 'Email address verified successfully.',
        );
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(
                message: 'Email address is already verified.',
            );
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(
            message: 'Verification email sent successfully.',
        );
    }
}
