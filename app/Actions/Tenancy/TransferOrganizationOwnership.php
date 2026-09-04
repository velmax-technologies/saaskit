<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferOrganizationOwnership
{
    public function execute(
        Organization $organization,
        OrganizationMember $target,
    ): OrganizationMember {
        if ($target->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'member' => [
                    'The member does not belong to this organization.',
                ],
            ]);
        }

        if ($target->status !== 'active') {
            throw ValidationException::withMessages([
                'member' => [
                    'Only active members can become organization owners.',
                ],
            ]);
        }

        if ($target->isOwner()) {
            throw ValidationException::withMessages([
                'member' => [
                    'This member is already the organization owner.',
                ],
            ]);
        }

        return DB::transaction(function () use ($organization, $target): OrganizationMember {
            $currentOwner = OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('role', OrganizationMemberRole::OWNER->value)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $currentOwner) {
                throw ValidationException::withMessages([
                    'organization' => [
                        'The organization does not have an active owner.',
                    ],
                ]);
            }

            $target->refresh();

            if (
                $target->organization_id !== $organization->id
                || $target->status !== 'active'
                || $target->isOwner()
            ) {
                throw ValidationException::withMessages([
                    'member' => [
                        'The selected member can no longer become the owner.',
                    ],
                ]);
            }

            $currentOwner->update([
                'role' => OrganizationMemberRole::ADMIN->value,
            ]);

            $target->update([
                'role' => OrganizationMemberRole::OWNER->value,
            ]);

            return $target->fresh([
                'user',
                'organization',
            ]);
        });
    }
}
