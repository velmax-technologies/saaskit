<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasPublicId;

    protected string $publicIdPrefix = 'tok';

    protected $hidden = [
        'id',
        'tokenable_type',
        'tokenable_id',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
