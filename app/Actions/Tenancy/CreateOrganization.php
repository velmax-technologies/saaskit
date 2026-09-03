<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateOrganization
{
    public function execute(
        User $user,
        string $name,
        string $slug,
        ?string $description = null,
    ): Organization {
        return DB::transaction(function () use (
            $user,
            $name,
            $slug,
            $description,
        ): Organization {
            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);

            OrganizationMember::create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'role' => OrganizationMemberRole::OWNER->value,
                'status' => 'active',
            ]);

            return $organization;
        });
    }
}
