<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

final class CreateAccessToken
{
    public function execute(
        User $user,
        string $name = 'api',
        array $abilities = ['*'],
    ): NewAccessToken {
        $expiration = config('sanctum.expiration');

        $expiresAt = $expiration
            ? now()->addMinutes((int) $expiration)
            : null;

        return $user->createToken(
            $name,
            $abilities,
            $expiresAt,
        );
    }
}
