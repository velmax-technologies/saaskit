<?php

namespace App\Enums;

enum OrganizationMemberRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
}
