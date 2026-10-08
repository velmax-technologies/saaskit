<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasPublicId;

    public static function publicIdPrefix(): string
    {
        return 'tok_';
    }
}
