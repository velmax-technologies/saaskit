<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
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

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/tokens', [AuthController::class, 'tokens']);
            Route::delete('/tokens/{token}', [AuthController::class, 'revokeToken']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('organizations')->group(function (): void {
        Route::get('/', [OrganizationController::class, 'index']);
        Route::post('/', [OrganizationController::class, 'store']);
        Route::patch('/{organization}', [OrganizationController::class, 'update']);
        Route::patch('/{organization}/ownership', [OrganizationController::class, 'transferOwnership']);
        Route::delete('/{organization}', [OrganizationController::class, 'destroy']);
        Route::get('/{organization}/members', [OrganizationController::class, 'members']);
        Route::post('/{organization}/members', [OrganizationController::class, 'storeMember']);
        Route::patch('/{organization}/members/{membership}', [OrganizationController::class, 'updateMember']);
        Route::delete('/{organization}/members/{membership}', [OrganizationController::class, 'destroyMember']);
    });

    Route::middleware([
        'auth:sanctum',
        'abilities:'.ApiAbility::PROFILE_READ,
    ])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
    });
});
