<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_only_organizations_with_an_active_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstOrganization = $this->createOrganization('first');
        $secondOrganization = $this->createOrganization('second');
        $inactiveOrganization = $this->createOrganization('inactive');
        $otherOrganization = $this->createOrganization('other');

        $this->addMembership($user, $firstOrganization);
        $this->addMembership($user, $secondOrganization);
        $this->addMembership($user, $inactiveOrganization, 'inactive');
        $this->addMembership($otherUser, $otherOrganization);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Organizations retrieved successfully.')
            ->assertJsonCount(2, 'data.organizations')
            ->assertJsonFragment(['id' => $firstOrganization->public_id])
            ->assertJsonFragment(['id' => $secondOrganization->public_id])
            ->assertJsonMissing(['id' => $inactiveOrganization->public_id])
            ->assertJsonMissing(['id' => $otherOrganization->public_id])
            ->assertJsonMissing(['id' => $firstOrganization->id])
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_organization_listing_is_paginated(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 16) as $number) {
            $organization = $this->createOrganization((string) $number);
            $this->addMembership($user, $organization);
        }

        $firstPage = $this->actingAs($user)
            ->getJson('/api/v1/organizations');

        $firstPage
            ->assertOk()
            ->assertJsonCount(15, 'data.organizations')
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.pagination.total', 16)
            ->assertJsonPath('data.pagination.links.next', fn (?string $url): bool => $url !== null);

        $secondPage = $this->actingAs($user)
            ->getJson('/api/v1/organizations?page=2');

        $secondPage
            ->assertOk()
            ->assertJsonCount(1, 'data.organizations')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.links.previous', fn (?string $url): bool => $url !== null)
            ->assertJsonPath('data.pagination.links.next', null);
    }

    public function test_organization_listing_requires_authentication(): void
    {
        $this->getJson('/api/v1/organizations')
            ->assertUnauthorized();
    }

    public function test_active_member_can_view_an_organization_by_its_public_id(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization('viewable');
        $this->addMembership($user, $organization);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations/'.$organization->public_id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Organization retrieved successfully.')
            ->assertJsonPath('data.organization.id', $organization->public_id)
            ->assertJsonPath('data.organization.name', $organization->name)
            ->assertJsonPath('data.organization.slug', $organization->slug)
            ->assertJsonMissing(['id' => $organization->id]);
    }

    public function test_user_without_an_active_membership_cannot_view_an_organization(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization('restricted');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations/'.$organization->public_id);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not authorized to perform this action.');
    }

    public function test_inactive_member_cannot_view_an_organization(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization('inactive-member');
        $this->addMembership($user, $organization, 'inactive');

        $this->actingAs($user)
            ->getJson('/api/v1/organizations/'.$organization->public_id)
            ->assertForbidden();
    }

    public function test_organization_detail_requires_authentication(): void
    {
        $organization = $this->createOrganization('authentication');

        $this->getJson('/api/v1/organizations/'.$organization->public_id)
            ->assertUnauthorized();
    }

    public function test_unknown_or_numeric_organization_identifiers_return_not_found(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization('numeric-id');
        $this->addMembership($user, $organization);

        $this->actingAs($user)
            ->getJson('/api/v1/organizations/org_01J00000000000000000000000')
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');

        $this->actingAs($user)
            ->getJson('/api/v1/organizations/'.$organization->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    private function createOrganization(string $suffix): Organization
    {
        return Organization::create([
            'name' => 'Organization '.$suffix,
            'slug' => 'organization-'.$suffix,
            'description' => 'Organization '.$suffix.' description.',
        ]);
    }

    private function addMembership(
        User $user,
        Organization $organization,
        string $status = 'active',
    ): OrganizationMember {
        return OrganizationMember::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => $status,
        ]);
    }
}
