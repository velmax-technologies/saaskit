<?php

namespace App\Support\Api;

final class ApiAbility
{
    public const PROFILE_READ = 'profile:read';

    public const ORGANIZATIONS_READ = 'organizations:read';

    public const ORGANIZATIONS_WRITE = 'organizations:write';

    public const INVITATIONS_MANAGE = 'invitations:manage';

    public const INVITATIONS_ACCEPT = 'invitations:accept';

    /**
     * Abilities issued to a standard token after login.
     *
     * Registration tokens intentionally remain profile-only until login.
     *
     * @return list<string>
     */
    public static function standard(): array
    {
        return [
            self::PROFILE_READ,
            self::ORGANIZATIONS_READ,
            self::ORGANIZATIONS_WRITE,
            self::INVITATIONS_MANAGE,
            self::INVITATIONS_ACCEPT,
        ];
    }
}
