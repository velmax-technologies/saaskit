<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationMemberStatus;
use App\Models\OrganizationMember;
use Illuminate\Validation\ValidationException;

final class LeaveOrganization
{
    public function execute(
        OrganizationMember $member,
    ): void {
        if ($member->status !== OrganizationMemberStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'organization' => [
                    'You are not an active member of this organization.',
                ],
            ]);
        }

        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'organization' => [
                    'The organization owner cannot leave. Transfer ownership first.',
                ],
            ]);
        }

        $member->update([
            'status' => OrganizationMemberStatus::LEFT->value,
        ]);
    }
}