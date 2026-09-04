<?php

namespace App\Actions\Tenancy;

use App\Models\OrganizationInvitation;

final readonly class CreateOrganizationInvitationResult
{
    public function __construct(
        public OrganizationInvitation $invitation,
        public string $token,
    ) {
    }
}
