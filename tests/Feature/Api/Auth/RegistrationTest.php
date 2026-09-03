<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account created successfully.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.name', 'Test User')
            ->assertJsonPath('data.user.email', 'test@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'First User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Second User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_registration_is_rate_limited(): void
    {
        config(['auth.rate_limits.register' => 2]);

        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertCreated();

        $payload['email'] = 'test2@example.com';

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertCreated();

        $payload['email'] = 'test3@example.com';

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response
            ->assertTooManyRequests()
            ->assertJson([
                'success' => false,
                'message' => 'Too Many Attempts.',
            ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth-register');
    }
}
