<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class PasswordResetNotificationTest extends TestCase
{
    public function test_reset_notification_uses_configured_frontend_url(): void
    {
        config([
            'app.password_reset_url' => 'https://frontend.example/reset-password',
        ]);

        $user = new User(['email' => 'person@example.com']);
        $notification = new ResetPassword('sample-reset-token');

        $mail = $notification->toMail($user);

        $this->assertSame(
            'https://frontend.example/reset-password?token=sample-reset-token&email=person%40example.com',
            $mail->actionUrl,
        );
    }
}
