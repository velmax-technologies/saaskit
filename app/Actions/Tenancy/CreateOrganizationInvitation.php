<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Actions\Tenancy\CreateOrganizationInvitationResult;

final class CreateOrganizationInvitation
{
    public function execute(
        User $invitedBy,
        Organization $organization,
        string $email,
        OrganizationMemberRole $role,
        
    ): CreateOrganizationInvitationResult {
   
        $email = strtolower(trim($email));

        return DB::transaction(function () use (
            $invitedBy,
            $organization,
            $email,
            $role,
         ): CreateOrganizationInvitationResult {
            $existingMember = OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->whereHas('user', function ($query) use ($email): void {
                    $query->where('email', $email);
                })
                ->where('status', 'active')
                ->exists();

            if ($existingMember) {
                throw ValidationException::withMessages([
                    'email' => [
                        'This user is already an active member of the organization.',
                    ],
                ]);
            }

            $pendingInvitation = OrganizationInvitation::query()
                ->where('organization_id', $organization->id)
                ->where('email', $email)
                ->where(
                    'status',
                    OrganizationInvitationStatus::PENDING->value,
                )
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingInvitation) {
                throw ValidationException::withMessages([
                    'email' => [
                        'A pending invitation already exists for this email address.',
                    ],
                ]);
            }

            $token = Str::random(64);

            $invitation = OrganizationInvitation::create([
                'organization_id' => $organization->id,
                'invited_by' => $invitedBy->id,
                'email' => $email,
                'role' => $role->value,
                'token_hash' => hash('sha256', $token),
                'status' => OrganizationInvitationStatus::PENDING->value,
                'expires_at' => now()->addDays(
                    (int) config(
                        'saaskit.organization.invitation_expire',
                        7,
                    ),
                ),
            ]);

            return new CreateOrganizationInvitationResult(
                invitation: $invitation,
                token: $token,
            );
        });
    }
}
