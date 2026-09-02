<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\Auth\UserResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUser $registerUser,
    ): JsonResponse {
        $user = $registerUser->execute(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        $token = $user->createToken(
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
    ): JsonResponse {
        $user = $loginUser->execute(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        $token = $user->createToken(
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
}
