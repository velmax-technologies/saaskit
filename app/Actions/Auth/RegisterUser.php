<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterUser
{
    public function execute(
        string $name,
        string $email,
        string $password,
    ): User {
        return DB::transaction(function () use ($name, $email, $password): User {
            return User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
        });
    }
}
