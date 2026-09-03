<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.public_id', $user->public_id)
            ->assertJsonPath('data.user.email', 'test@example.com');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
                'errors' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_login_rejects_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
                'errors' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_token_has_configured_expiration(): void
    {
        config(['sanctum.expiration' => 43200]);

        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSuccessful();

        $token = $user->tokens()->latest('id')->first();

        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);

        $this->assertEqualsWithDelta(
            now()->addMinutes(43200)->timestamp,
            $token->expires_at->timestamp,
            5,
        );
    }

    public function test_expired_login_token_cannot_access_authenticated_endpoints(): void
    {
        config(['sanctum.expiration' => 43200]);

        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'expired-test',
            abilities: ['*'],
            expiresAt: now()->subMinute(),
        );

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_login_is_rate_limited(): void
    {
        config(['auth.rate_limits.login' => 2]);

        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertUnauthorized();

        $response = $this->postJson('/api/v1/auth/login', $payload);

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

        $this->app['cache']->flush();
    }
}
