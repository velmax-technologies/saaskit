<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_api_health_uses_standard_success_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'SaaSKit API is healthy.',
                'data' => null,
            ]);
    }

    public function test_registration_validation_uses_json_error_response(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'name',
                    'email',
                    'password',
                ],
            ]);
    }
    public function test_unauthenticated_api_request_uses_standard_error_response(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => null,
            ]);
    }

    public function test_missing_api_route_uses_standard_error_response(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'The route api/v1/does-not-exist could not be found.',
                'errors' => null,
            ]);
    }

    public function test_unsupported_api_method_uses_standard_error_response(): void
    {
        $response = $this->getJson('/api/v1/auth/login');

        $response
            ->assertMethodNotAllowed()
            ->assertJson([
                'success' => false,
                'message' => 'The GET method is not supported for route api/v1/auth/login. Supported methods: POST.',
                'errors' => null,
            ]);
    }

}
