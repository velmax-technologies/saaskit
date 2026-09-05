<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResendOrganizationInvitation
{
    public function execute(
        Organization $organization,
        OrganizationInvitation $invitation,
    ): ResendOrganizationInvitationResult {
        if ($invitation->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'The invitation does not belong to this organization.',
                ],
            ]);
        }

        if (
            $invitation->status !== OrganizationInvitationStatus::PENDING
            && ! $invitation->isExpired()
        ) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'Only pending invitations can be resent.',
                ],
            ]);
        }

        return DB::transaction(function () use ($invitation): ResendOrganizationInvitationResult {
            $token = Str::random(64);

            $invitation->update([
                'token_hash' => hash('sha256', $token),
                'status' => OrganizationInvitationStatus::PENDING->value,
                'expires_at' => now()->addDays(
                    (int) config(
                        'saaskit.organization.invitation_expire',
                        7,
                    ),
                ),
                'accepted_at' => null,
                'cancelled_at' => null,
            ]);

            return new ResendOrganizationInvitationResult(
                invitation: $invitation->fresh([
                    'organization',
                    'inviter',
                ]),
                token: $token,
            );
        });
    }
}
