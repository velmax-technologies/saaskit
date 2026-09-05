<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Enums\OrganizationMemberStatus;

final class AcceptOrganizationInvitation
{
    public function execute(
        User $user,
        OrganizationInvitation $invitation,
        string $token,
    ): OrganizationMember {
        if (! hash_equals(
            $invitation->token_hash,
            hash('sha256', $token),
        )) {
            throw ValidationException::withMessages([
                'token' => [
                    'The invitation token is invalid.',
                ],
            ]);
        }

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'This invitation is no longer pending.',
                ],
            ]);
        }

        if ($invitation->expires_at->isPast()) {
            $invitation->update([
                'status' => OrganizationInvitationStatus::EXPIRED->value,
            ]);

            throw ValidationException::withMessages([
                'invitation' => [
                    'This invitation has expired.',
                ],
            ]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'This invitation was sent to a different email address.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $invitation,
        ): OrganizationMember {
            $existingMembership = OrganizationMember::query()
                ->where('organization_id', $invitation->organization_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (
                $existingMembership
                && $existingMembership->status === OrganizationMemberStatus::ACTIVE
            ) {
                throw ValidationException::withMessages([
                    'invitation' => [
                        'You are already a member of this organization.',
                    ],
                ]);
            }

            if ($existingMembership) {
                $existingMembership->update([
                    'role' => $invitation->role->value,
                    'status' => OrganizationMemberStatus::ACTIVE->value,
                ]);

                $membership = $existingMembership->fresh([
                    'user',
                    'organization',
                ]);
            } else {
                $membership = OrganizationMember::create([
                    'user_id' => $user->id,
                    'organization_id' => $invitation->organization_id,
                    'role' => $invitation->role->value,
                    'status' => OrganizationMemberStatus::ACTIVE->value,
                ]);
            }

            $invitation->update([
                'status' => OrganizationInvitationStatus::ACCEPTED->value,
                'accepted_at' => now(),
            ]);

            return $membership;
        });
    }
}
