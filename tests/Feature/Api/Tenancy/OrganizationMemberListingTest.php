<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationMemberListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_owner_can_list_organization_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        OrganizationMember::create([
            'user_id' => $member->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.members.0.id',
                fn ($id) => str_starts_with($id, 'mem_'),
            );

        $response->assertJsonFragment([
            'id' => $member->public_id,
            'name' => $member->name,
            'email' => $member->email,
        ]);
    }

    public function test_admin_can_list_members(): void
    {
        $admin = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $admin->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        )->assertOk();
    }

    public function test_member_can_list_members(): void
    {
        $member = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $member->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        )->assertOk();
    }

    public function test_unauthenticated_user_cannot_list_members(): void
    {
        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        )->assertUnauthorized();
    }

    public function test_user_from_another_organization_cannot_list_members(): void
    {
        $user = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        )->assertForbidden();
    }

    public function test_left_member_cannot_list_members(): void
    {
        $user = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => OrganizationMemberStatus::LEFT->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        )->assertForbidden();
    }

    public function test_members_from_another_organization_are_not_returned(): void
    {
        $user = User::factory()->create();
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
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        OrganizationMember::create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrganization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'email' => $otherUser->email,
            ]);
    }

    public function test_numeric_ids_are_not_exposed(): void
    {
        $user = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members"
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.members.0.user_id')
            ->assertJsonMissingPath('data.members.0.organization_id')
            ->assertJsonMissingPath('data.members.0.id.0');
    }

    public function test_members_are_paginated(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);

        for ($i = 0; $i < 20; $i++) {
            $memberUser = User::factory()->create();

            OrganizationMember::create([
                'user_id' => $memberUser->id,
                'organization_id' => $organization->id,
                'role' => OrganizationMemberRole::MEMBER->value,
                'status' => OrganizationMemberStatus::ACTIVE->value,
            ]);
        }

        Sanctum::actingAs($owner);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/members?per_page=10"
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 21);
    }
}
