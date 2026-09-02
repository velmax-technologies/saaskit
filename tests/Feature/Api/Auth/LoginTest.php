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
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.email.0',
                'The provided credentials are incorrect.',
            );

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
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
