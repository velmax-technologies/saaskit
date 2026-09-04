<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_leave_organization(): void
    {
        $admin = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->admin()
            ->create([
                'user_id' => $admin->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($admin)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'user_id' => $admin->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'inactive',
        ]);
    }

    public function test_member_can_leave_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'inactive',
        ]);
    }

    public function test_owner_cannot_leave_organization(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        $ownerMember = OrganizationMember::factory()
            ->owner()
            ->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownerMember->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);
    }

    public function test_inactive_member_cannot_leave_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->inactive()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'inactive',
        ]);
    }

    public function test_non_member_cannot_leave_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertNotFound();
    }

    public function test_member_of_another_organization_cannot_leave_this_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $otherOrganization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'organization_id' => $otherOrganization->id,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_user_cannot_leave_organization(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/leave",
        );

        $response->assertUnauthorized();
    }

    public function test_leaving_does_not_delete_membership_record(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            )
            ->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseCount('organization_members', 1);
    }

    public function test_member_who_left_no_longer_appears_in_active_member_listing(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $ownerMember = OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
            ]);

        $member = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            )
            ->assertNoContent();

        $owner = User::query()->findOrFail($ownerMember->user_id);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/members",
            );

        $response
            ->assertSuccessful()
            ->assertJsonMissing([
                'id' => $member->public_id,
            ]);
    }

    public function test_leaving_does_not_expose_numeric_ids(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/leave",
            );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'status' => 'inactive',
        ]);
    }
}
