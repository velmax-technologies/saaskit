<?php

namespace App\Actions\Tenancy;

use App\Models\OrganizationInvitation;

final readonly class ResendOrganizationInvitationResult
{
    public function __construct(
        public OrganizationInvitation $invitation,
        public string $token,
    ) {
    }
}
