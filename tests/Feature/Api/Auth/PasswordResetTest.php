<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth-forgot-password');
    }

    public function test_user_can_request_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'If an account exists for that email address, a password reset link has been sent.',
                'data' => null,
            ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
        );
    }

    public function test_unknown_email_returns_same_response(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'If an account exists for that email address, a password reset link has been sent.',
                'data' => null,
            ]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        config([
            'auth.rate_limits.forgot_password' => 2,
        ]);

        $user = User::factory()->create();

        $payload = [
            'email' => $user->email,
        ];

        $this->postJson('/api/v1/auth/forgot-password', $payload)
            ->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', $payload)
            ->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', $payload)
            ->assertStatus(429);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $response = $this->postJson('/api/v1/auth/reset-password', [
                    'email' => $user->email,
                    'token' => $notification->token,
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]);

                return $response->isSuccessful();
            },
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check('NewPassword123!', $user->password)
        );
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The password reset token is invalid or has expired.',
            ]);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'token',
            'password' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_valid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'token',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_revokes_existing_sessions(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'api',
            abilities: ['*'],
        );

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $response = $this->postJson('/api/v1/auth/reset-password', [
                    'email' => $user->email,
                    'token' => $notification->token,
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]);

                return $response->isSuccessful();
            },
        );

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $payload = [
                    'email' => $user->email,
                    'token' => $notification->token,
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ];

                $first = $this->postJson(
                    '/api/v1/auth/reset-password',
                    $payload
                );

                $second = $this->postJson(
                    '/api/v1/auth/reset-password',
                    [
                        ...$payload,
                        'password' => 'AnotherPassword123!',
                        'password_confirmation' => 'AnotherPassword123!',
                    ]
                );

                return $first->isSuccessful()
                    && $second->status() === 422;
            },
        );
    }
}
