<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final class ResetUserPassword
{
    public function execute(
        string $email,
        string $token,
        string $password,
    ): bool {
        $status = Password::broker()->reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => $password,
                    ])->save();

                    $user->tokens()->delete();
                });
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
