<?php

namespace App\Policies;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasAnyRole(
            $user,
            $organization,
            OrganizationMemberRole::OWNER,
            OrganizationMemberRole::ADMIN,
        );
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->hasRole(
            $user,
            $organization,
            OrganizationMemberRole::OWNER,
        );
    }

    public function viewMembers(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization);
    }

    public function inviteMembers(User $user, Organization $organization): bool
    {
        return $this->hasAnyRole(
            $user,
            $organization,
            OrganizationMemberRole::OWNER,
            OrganizationMemberRole::ADMIN,
        );
    }

    public function updateMemberRoles(User $user, Organization $organization): bool
    {
        return $this->hasRole(
            $user,
            $organization,
            OrganizationMemberRole::OWNER,
        );
    }

    public function removeMembers(
        User $user,
        Organization $organization,
        OrganizationMember $member,
    ): bool {
        if ($member->organization_id !== $organization->id) {
            return false;
        }

        if ($member->status !== 'active') {
            return false;
        }

        if ($member->isOwner()) {
            return false;
        }

        if ($user->id === $member->user_id) {
            return false;
        }

        if ($this->hasRole(
            $user,
            $organization,
            OrganizationMemberRole::OWNER,
        )) {
            return true;
        }

        return $this->hasRole(
            $user,
            $organization,
            OrganizationMemberRole::ADMIN,
        ) && $member->isMember();
    }

    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $this->hasRole(
            $user,
            $organization,
            OrganizationMemberRole::OWNER,
        );
    }

    public function leave(User $user, Organization $organization): bool
    {
        return $this->hasAnyRole(
            $user,
            $organization,
            OrganizationMemberRole::ADMIN,
            OrganizationMemberRole::MEMBER,
        );
    }

    private function hasActiveMembership(
        User $user,
        Organization $organization,
    ): bool {
        return $organization->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    private function hasRole(
        User $user,
        Organization $organization,
        OrganizationMemberRole $role,
    ): bool {
        return $organization->members()
            ->where('user_id', $user->id)
            ->where('role', $role->value)
            ->where('status', 'active')
            ->exists();
    }

    private function hasAnyRole(
        User $user,
        Organization $organization,
        OrganizationMemberRole ...$roles,
    ): bool {
        return $organization->members()
            ->where('user_id', $user->id)
            ->whereIn(
                'role',
                array_map(
                    static fn (OrganizationMemberRole $role): string => $role->value,
                    $roles,
                ),
            )
            ->where('status', 'active')
            ->exists();
    }
}
