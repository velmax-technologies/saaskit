<?php

namespace Tests\Feature\Api\Tenancy;

use App\Models\Organization;
use App\Enums\OrganizationMemberStatus;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationMemberRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::REMOVED->value,
        ]);
    }

    public function test_owner_can_remove_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->admin()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $admin->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::REMOVED->value,
        ]);
    }

    public function test_admin_can_remove_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->admin()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $admin->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::REMOVED->value,
        ]);
    }

    public function test_admin_cannot_remove_admin(): void
    {
        $admin = User::factory()->create();
        $targetAdmin = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->admin()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $admin->id,
            ]);

        $target = OrganizationMember::factory()
            ->admin()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $targetAdmin->id,
            ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_cannot_remove_owner(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->admin()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $admin->id,
            ]);

        $target = OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_owner_cannot_remove_owner(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        $target = OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_member_cannot_remove_member(): void
    {
        $member = User::factory()->create();
        $targetUser = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $targetUser->id,
            ]);

        Sanctum::actingAs($member);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_member_cannot_remove_admin(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        $target = OrganizationMember::factory()
            ->admin()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $admin->id,
            ]);

        Sanctum::actingAs($member);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_member_cannot_remove_themselves(): void
    {
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($member);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_unauthenticated_user_cannot_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_member_from_another_organization_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $otherOrganization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_inactive_member_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'status' => OrganizationMemberStatus::LEFT->value,
        ]);
    }

    public function test_removal_does_not_delete_membership_record(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'status' => OrganizationMemberStatus::REMOVED->value,
        ]);
    }

    public function test_removed_member_is_no_longer_returned_in_member_listing(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($owner);

        $deleteResponse = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $deleteResponse->assertNoContent();

        $listResponse = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members",
        );

        $listResponse
            ->assertOk()
            ->assertJsonCount(1, 'data.members');
    }

    public function test_response_does_not_expose_numeric_database_ids(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
            ]);

        $target = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson(
            "/api/v1/organizations/{$organization->public_id}/members/{$target->public_id}",
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
             'status' => OrganizationMemberStatus::REMOVED->value,
        ]);

        $this->assertNotNull($target->id);
        $this->assertNotNull($target->organization_id);
        $this->assertNotNull($target->user_id);
    }
}
