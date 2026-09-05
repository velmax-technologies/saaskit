<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationMemberStatus;
use App\Models\OrganizationMember;
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

        if ($member->status === OrganizationMemberStatus::REMOVED) {
            throw ValidationException::withMessages([
                'organization' => [
                    'You cannot rejoin an organization after being removed. You must be invited again.',
                ],
            ]);
        }

        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'organization' => [
                    'The organization owner cannot rejoin as a former member.',
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