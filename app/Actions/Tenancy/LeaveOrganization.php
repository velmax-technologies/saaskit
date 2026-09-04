<?php

namespace App\Actions\Tenancy;

use App\Models\OrganizationMember;
use Illuminate\Validation\ValidationException;

final class LeaveOrganization
{
    public function execute(
        OrganizationMember $member,
    ): void {
        if ($member->status !== 'active') {
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
            'status' => 'inactive',
        ]);
    }
}
