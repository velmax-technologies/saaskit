<?php

namespace App\Enums;

enum OrganizationMemberStatus: string
{
    case ACTIVE = 'active';
    case LEFT = 'left';
    case REMOVED = 'removed';
}
