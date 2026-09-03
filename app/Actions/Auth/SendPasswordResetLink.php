<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

final class SendPasswordResetLink
{
    public function execute(string $email): void
    {
        Password::broker()->sendResetLink([
            'email' => $email,
        ]);
    }
}
