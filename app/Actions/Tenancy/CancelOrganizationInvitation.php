<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Validation\ValidationException;

final class CancelOrganizationInvitation
{
    public function execute(
        Organization $organization,
        OrganizationInvitation $invitation,
    ): void {
        if ($invitation->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'The invitation does not belong to this organization.',
                ],
            ]);
        }

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'Only pending invitations can be cancelled.',
                ],
            ]);
        }

        $invitation->update([
            'status' => OrganizationInvitationStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);
    }
}
