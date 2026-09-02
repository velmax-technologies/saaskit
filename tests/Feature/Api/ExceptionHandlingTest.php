<?php

namespace Tests\Feature\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/test/validation', function () {
            throw ValidationException::withMessages([
                'email' => ['The email field is required.'],
            ]);
        });

        Route::get('/api/v1/test/authentication', function () {
            throw new AuthenticationException;
        });

        Route::get('/api/v1/test/authorization', function () {
            throw new AuthorizationException;
        });

        Route::get('/api/v1/test/not-found', function () {
            throw new ModelNotFoundException;
        });

        Route::get('/api/v1/test/server-error', function () {
            throw new \RuntimeException('Test exception.');
        });
    }

    public function test_validation_exception_returns_standard_api_response(): void
    {
        $response = $this->getJson('/api/v1/test/validation');

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'email' => ['The email field is required.'],
                ],
            ]);
    }

    public function test_authentication_exception_returns_standard_api_response(): void
    {
        $response = $this->getJson('/api/v1/test/authentication');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => null,
            ]);
    }

    public function test_authorization_exception_returns_standard_api_response(): void
    {
        $response = $this->getJson('/api/v1/test/authorization');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
                'errors' => null,
            ]);
    }

    public function test_model_not_found_exception_returns_standard_api_response(): void
    {
        $response = $this->getJson('/api/v1/test/not-found');

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => null,
            ]);
    }

    public function test_unexpected_exception_returns_standard_api_response(): void
    {
        $response = $this->getJson('/api/v1/test/server-error');

        $response
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }
}
