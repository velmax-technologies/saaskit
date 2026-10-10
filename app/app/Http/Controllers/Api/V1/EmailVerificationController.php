<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        // The signed URL is public, but its signature and expiry are enforced
        // by the route middleware.
        $user = User::query()->findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
        );

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success('Email address is already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return ApiResponse::success('Email address verified successfully.');
    }
}
