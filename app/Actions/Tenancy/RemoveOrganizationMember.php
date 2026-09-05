<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationMemberStatus;
use App\Models\OrganizationMember;
use Illuminate\Validation\ValidationException;

final class RemoveOrganizationMember
{
    public function execute(
        OrganizationMember $member,
    ): void {
        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'member' => [
                    'The organization owner cannot be removed.',
                ],
            ]);
        }

        if ($member->status !== OrganizationMemberStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'member' => [
                    'Only active organization members can be removed.',
                ],
            ]);
        }

        $member->update([
            'status' => OrganizationMemberStatus::REMOVED->value,
        ]);
    }
}