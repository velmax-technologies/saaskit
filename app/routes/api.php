<?php

use App\Http\Controllers\Api\V1\AcceptOrganizationInvitationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationInvitationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Support\Api\ApiAbility;
use App\Support\Api\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return ApiResponse::success('SaaSKit API is healthy.');
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        // Password reset endpoints are public; reset tokens authorize the change.
        Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:5,1');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
                ->middleware('throttle:6,1');
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/tokens', [AuthController::class, 'tokens']);
            Route::delete('/tokens/{token}', [AuthController::class, 'revokeToken']);
        });
    });

    Route::middleware([
        'auth:sanctum',
        'verified',
        'abilities:'.ApiAbility::INVITATIONS_ACCEPT,
    ])->post(
        '/invitations/{invitation}/accept',
        AcceptOrganizationInvitationController::class,
    );

    Route::middleware(['auth:sanctum', 'verified'])->prefix('organizations')->group(function (): void {
        Route::middleware('abilities:'.ApiAbility::ORGANIZATIONS_READ)->group(function (): void {
            Route::get('/', [OrganizationController::class, 'index']);
            Route::get('/{organization}/members', [OrganizationController::class, 'members']);
        });

        Route::middleware('abilities:'.ApiAbility::ORGANIZATIONS_WRITE)->group(function (): void {
            Route::post('/', [OrganizationController::class, 'store']);
            Route::patch('/{organization}', [OrganizationController::class, 'update']);
            Route::patch('/{organization}/ownership', [OrganizationController::class, 'transferOwnership']);
            Route::delete('/{organization}', [OrganizationController::class, 'destroy']);
            Route::post('/{organization}/members', [OrganizationController::class, 'storeMember']);
            Route::patch('/{organization}/members/{membership}', [OrganizationController::class, 'updateMember']);
            Route::delete('/{organization}/members/{membership}', [OrganizationController::class, 'destroyMember']);
        });

        Route::middleware('abilities:'.ApiAbility::INVITATIONS_MANAGE)->group(function (): void {
            Route::get('/{organization}/invitations', [OrganizationInvitationController::class, 'index']);
            Route::post('/{organization}/invitations', [OrganizationInvitationController::class, 'store']);
            Route::post('/{organization}/invitations/{invitation}/resend', [OrganizationInvitationController::class, 'resend']);
            Route::delete('/{organization}/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy']);
        });
    });

    Route::middleware([
        'auth:sanctum',
        'abilities:'.ApiAbility::PROFILE_READ,
    ])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
    });
});
