<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationMemberRoleUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_promote_member_to_admin(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $member = OrganizationMember::create([
            'user_id' => $memberUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$member->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.member.id',
                fn (string $id): bool => str_starts_with($id, 'mem_'),
            )
            ->assertJsonPath(
                'data.member.role',
                OrganizationMemberRole::ADMIN->value,
            );

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'role' => OrganizationMemberRole::ADMIN->value,
        ]);
    }

    public function test_owner_can_demote_admin_to_member(): void
    {
        $owner = User::factory()->create();
        $adminUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $admin = OrganizationMember::create([
            'user_id' => $adminUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$admin->public_id}",
                [
                    'role' => OrganizationMemberRole::MEMBER->value,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.member.role',
                OrganizationMemberRole::MEMBER->value,
            );

        $this->assertDatabaseHas('organization_members', [
            'id' => $admin->id,
            'role' => OrganizationMemberRole::MEMBER->value,
        ]);
    }

    public function test_admin_cannot_update_member_role(): void
    {
        $admin = User::factory()->create();
        $memberUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $admin->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'active',
        ]);

        $member = OrganizationMember::create([
            'user_id' => $memberUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$member->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            )
            ->assertForbidden();
    }

    public function test_member_cannot_update_member_role(): void
    {
        $memberUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $memberUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $target = OrganizationMember::create([
            'user_id' => $targetUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $this->actingAs($memberUser)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            )
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_update_member_role(): void
    {
        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->patchJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$member->public_id}",
            [
                'role' => OrganizationMemberRole::ADMIN->value,
            ],
        )->assertUnauthorized();
    }

    public function test_owner_cannot_change_an_owner_to_admin_or_member(): void
    {
        $owner = User::factory()->create();
        $targetOwnerUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $targetOwner = OrganizationMember::create([
            'user_id' => $targetOwnerUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$targetOwner->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseHas('organization_members', [
            'id' => $targetOwner->id,
            'role' => OrganizationMemberRole::OWNER->value,
        ]);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $member = OrganizationMember::create([
            'user_id' => $memberUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$member->public_id}",
                [
                    'role' => 'owner',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_inactive_member_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $member = OrganizationMember::create([
            'user_id' => $memberUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'inactive',
        ]);

        $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$member->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            )
            ->assertNotFound();
    }

    public function test_member_from_another_organization_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $otherOrganization = Organization::create([
            'name' => 'Other',
            'slug' => 'other',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $otherMember = OrganizationMember::create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrganization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$otherMember->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            )
            ->assertNotFound();

        $this->assertDatabaseHas('organization_members', [
            'id' => $otherMember->id,
            'role' => OrganizationMemberRole::MEMBER->value,
        ]);
    }

    public function test_response_does_not_expose_numeric_database_ids(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $member = OrganizationMember::create([
            'user_id' => $memberUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$member->public_id}",
                [
                    'role' => OrganizationMemberRole::ADMIN->value,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.member.id',
                fn (string $id): bool => str_starts_with($id, 'mem_'),
            )
            ->assertJsonMissingPath('data.member.user_id')
            ->assertJsonMissingPath('data.member.organization_id');
    }
}
