<?php

namespace Tests\Unit\Support\Api;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_response(): void
    {
        $response = ApiResponse::success(
            ['id' => 'usr_test'],
            'User retrieved successfully.',
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => ['id' => 'usr_test'],
        ], $response->getData(true));
    }

    public function test_created_response(): void
    {
        $response = ApiResponse::created(
            ['id' => 'usr_test'],
            'User created successfully.',
        );

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_validation_error_response(): void
    {
        $errors = [
            'email' => ['The email field is required.'],
        ];

        $response = ApiResponse::validationError($errors);

        $this->assertSame(422, $response->getStatusCode());

        $this->assertSame([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors,
        ], $response->getData(true));
    }

    public function test_unauthorized_response(): void
    {
        $response = ApiResponse::unauthorized();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_forbidden_response(): void
    {
        $response = ApiResponse::forbidden();

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_not_found_response(): void
    {
        $response = ApiResponse::notFound();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_conflict_response(): void
    {
        $response = ApiResponse::conflict();

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_too_many_requests_response(): void
    {
        $response = ApiResponse::tooManyRequests();

        $this->assertSame(429, $response->getStatusCode());
    }

    public function test_server_error_response(): void
    {
        $response = ApiResponse::serverError();

        $this->assertSame(500, $response->getStatusCode());
    }
}
