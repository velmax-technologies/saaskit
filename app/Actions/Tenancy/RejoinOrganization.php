<?php

namespace App\Actions\Tenancy;

use App\Models\OrganizationMember;
use App\Enums\OrganizationMemberStatus;
use Illuminate\Validation\ValidationException;

final class RejoinOrganization
{
    public function execute(
        OrganizationMember $member,
    ): OrganizationMember {
        if ($member->status === OrganizationMemberStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'organization' => [
                    'You are already an active member of this organization.',
                ],
            ]);
        }

        if ($member->role->value === 'owner') {
            throw ValidationException::withMessages([
                'organization' => [
                    'The organization owner cannot rejoin as an inactive owner.',
                ],
            ]);
        }

        $member->update([
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        return $member->fresh([
            'user',
            'organization',
        ]);
    }
}
