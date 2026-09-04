<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\OrganizationMember;
use Illuminate\Validation\ValidationException;

final class UpdateOrganizationMemberRole
{
    public function execute(
        OrganizationMember $member,
        OrganizationMemberRole $role,
    ): OrganizationMember {
        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'role' => [
                    'The organization owner role cannot be changed.',
                ],
            ]);
        }

        $member->update([
            'role' => $role->value,
        ]);

        return $member->fresh(['user', 'organization']);
    }
}
