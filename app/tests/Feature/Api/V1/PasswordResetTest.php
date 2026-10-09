<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'known@example.com',
        ]);

        $knownResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $knownResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'If an account exists for this email, a password reset link has been sent.',
            );

        Notification::assertSentTo($user, ResetPassword::class);

        $unknownResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $unknownResponse->assertOk();
        $this->assertSame($knownResponse->json(), $unknownResponse->json());

        Notification::assertNothingSentTo(User::query()
            ->where('email', 'unknown@example.com')
            ->first() ?? new User(['email' => 'unknown@example.com']), ResetPassword::class);
    }

    public function test_forgot_password_validates_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_valid_reset_changes_password_and_revokes_existing_api_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'old-password',
        ]);

        $user->createToken('existing-device');
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password has been reset successfully.');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);

        \DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(
                config('auth.passwords.users.expire') + 1,
            )]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_password_validates_confirmation_and_minimum_length(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => 'any-token',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.21',
            ])->postJson('/api/v1/auth/forgot-password', [
                'email' => 'unknown@example.com',
            ])->assertOk();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.21',
        ])->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertStatus(429);
    }

    public function test_reset_password_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.22',
            ])->postJson('/api/v1/auth/reset-password', [
                'email' => 'reset@example.com',
                'token' => 'invalid-token',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertUnprocessable();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.22',
        ])->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => 'invalid-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(429);
    }
}
