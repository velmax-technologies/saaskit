<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'Saaskit API is healthy.',
            'data' => [
                'version' => 'v1',
            ],
        ]);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login');

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:auth-forgot-password');

        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);

            Route::get('/sessions', [AuthController::class, 'sessions']);
            Route::delete('/sessions/{publicId}', [AuthController::class, 'revokeSession']);
            Route::delete('/sessions', [AuthController::class, 'revokeOtherSessions']);
        });

    });

});
