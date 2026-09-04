<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | API Health
    |--------------------------------------------------------------------------
    */

    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'Saaskit API is healthy.',
            'data' => [
                'version' => 'v1',
            ],
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Authentication - Public
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function (): void {

        Route::post('/register', [
            AuthController::class,
            'register',
        ])->middleware('throttle:auth-register');

        Route::post('/login', [
            AuthController::class,
            'login',
        ])->middleware('throttle:auth-login');

        Route::post('/forgot-password', [
            AuthController::class,
            'forgotPassword',
        ])->middleware('throttle:auth-forgot-password');

        Route::post('/reset-password', [
            AuthController::class,
            'resetPassword',
        ]);

        Route::get('/email/verify/{publicId}/{hash}', [
            EmailVerificationController::class,
            'verify',
        ])
            ->middleware('signed')
            ->name('verification.verify');

        /*
        |--------------------------------------------------------------------------
        | Authentication - Protected
        |--------------------------------------------------------------------------
        */

        Route::middleware('auth:sanctum')->group(function (): void {

            Route::get('/me', [
                AuthController::class,
                'me',
            ]);

            Route::post('/logout', [
                AuthController::class,
                'logout',
            ]);

            Route::get('/sessions', [
                AuthController::class,
                'sessions',
            ]);

            Route::delete('/sessions/{publicId}', [
                AuthController::class,
                'revokeSession',
            ]);

            Route::delete('/sessions', [
                AuthController::class,
                'revokeOtherSessions',
            ]);

            Route::post('/email/resend-verification', [
                EmailVerificationController::class,
                'resend',
            ])->middleware('throttle:auth-verification');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Organizations - Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')
        ->prefix('organizations')
        ->group(function (): void {

            Route::get('/', [
                OrganizationController::class,
                'index',
            ]);

            Route::post('/', [
                OrganizationController::class,
                'store',
            ]);

            Route::get('/{organization}', [
                OrganizationController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Organization Members
            |--------------------------------------------------------------------------
            */

            Route::get('/{organization}/members', [
                OrganizationMemberController::class,
                'index',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Organization Invitations
            |--------------------------------------------------------------------------
            */

            Route::post('/{organization}/invitations', [
                OrganizationInvitationController::class,
                'store',
            ]);

            Route::get('/{organization}/invitations', [
                OrganizationInvitationController::class,
                'index',
            ]);

            Route::post(
                '/{organization}/invitations/{invitation}/resend',
                [
                    OrganizationInvitationController::class,
                    'resend',
                ],
            );

            Route::delete(
                '/{organization}/invitations/{invitation}',
                [
                    OrganizationInvitationController::class,
                    'cancel',
                ],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Invitations - Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function (): void {

        Route::post('/invitations/{invitation}/accept', [
            OrganizationInvitationController::class,
            'accept',
        ]);
    });
});
