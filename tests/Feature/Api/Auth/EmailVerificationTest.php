<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $email = 'verification-'.uniqid().'@example.com';

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Verification User',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $user = User::where('email', $email)->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
        );
    }

    public function test_user_can_verify_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'publicId' => $user->public_id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $response = $this->getJson($url);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Email address verified successfully.'
            );

        $this->assertNotNull(
            $user->fresh()->email_verified_at
        );
    }

    public function test_invalid_verification_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'publicId' => $user->public_id,
                'hash' => sha1('wrong@example.com'),
            ],
        );

        $response = $this->getJson($url);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNull(
            $user->fresh()->email_verified_at
        );
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = route('verification.verify', [
            'publicId' => $user->public_id,
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => now()->addMinutes(60)->timestamp,
            'signature' => 'invalid-signature',
        ]);

        $this->getJson($url)
            ->assertForbidden();

        $this->assertNull(
            $user->fresh()->email_verified_at
        );
    }

    public function test_verification_url_does_not_expose_database_id(): void
    {
        $user = User::factory()->unverified()->create();

        $notification = new VerifyEmail;

        $mail = $notification->toMail($user);

        $this->assertStringContainsString(
            '/'.$user->public_id.'/',
            $mail->actionUrl
        );

        $this->assertStringNotContainsString(
            '/'.$user->getKey().'/',
            $mail->actionUrl
        );
    }
}
