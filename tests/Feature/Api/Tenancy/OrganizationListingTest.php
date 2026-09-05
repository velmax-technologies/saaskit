<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_their_organizations(): void
    {
        $user = User::factory()->create();

        $organizationOne = $this->createOrganization('one');
        $organizationTwo = $this->createOrganization('two');

        $this->addMembership($user, $organizationOne);
        $this->addMembership($user, $organizationTwo);

        $otherOrganization = $this->createOrganization('other');
        $otherUser = User::factory()->create();

        $this->addMembership($otherUser, $otherOrganization);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Organizations retrieved successfully.',
            )
            ->assertJsonCount(2, 'data.organizations');

        $response->assertJsonPath(
            'data.organizations.0.id',
            fn (string $id): bool => str_starts_with($id, 'org_'),
        );

        $response->assertJsonPath(
            'data.organizations.1.id',
            fn (string $id): bool => str_starts_with($id, 'org_'),
        );
    }

    public function test_organization_listing_requires_authentication(): void
    {
        $this->getJson('/api/v1/organizations')
            ->assertUnauthorized();
    }

    public function test_left_member_cannot_list_organization(): void
    {
        $user = User::factory()->create();

        $organization = $this->createOrganization('left-member');

        $this->addMembership(
            $user,
            $organization,
            OrganizationMemberStatus::LEFT,
        );

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.organizations');
    }

    public function test_removed_member_cannot_list_organization(): void
    {
        $user = User::factory()->create();

        $organization = $this->createOrganization('removed-member');

        $this->addMembership(
            $user,
            $organization,
            OrganizationMemberStatus::REMOVED,
        );

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.organizations');
    }

    public function test_organization_listing_only_returns_active_memberships(): void
    {
        $user = User::factory()->create();

        $activeOrganization = $this->createOrganization('active');
        $leftOrganization = $this->createOrganization('left');
        $removedOrganization = $this->createOrganization('removed');

        $this->addMembership(
            $user,
            $activeOrganization,
            OrganizationMemberStatus::ACTIVE,
        );

        $this->addMembership(
            $user,
            $leftOrganization,
            OrganizationMemberStatus::LEFT,
        );

        $this->addMembership(
            $user,
            $removedOrganization,
            OrganizationMemberStatus::REMOVED,
        );

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.organizations')
            ->assertJsonPath(
                'data.organizations.0.id',
                $activeOrganization->public_id,
            );
    }

    public function test_user_cannot_view_an_organization_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization('private');

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/organizations/'.$organization->public_id,
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'You are not authorized to perform this action.',
            );
    }

    public function test_active_member_can_view_an_organization(): void
    {
        $user = User::factory()->create();

        $organization = $this->createOrganization('active-member');

        $this->addMembership(
            $user,
            $organization,
            OrganizationMemberStatus::ACTIVE,
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/organizations/'.$organization->public_id,
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.organization.id',
                $organization->public_id,
            )
            ->assertJsonPath(
                'data.organization.name',
                $organization->name,
            );
    }

    public function test_left_member_cannot_view_an_organization(): void
    {
        $user = User::factory()->create();

        $organization = $this->createOrganization('left-member');

        $this->addMembership(
            $user,
            $organization,
            OrganizationMemberStatus::LEFT,
        );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/organizations/'.$organization->public_id,
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'You are not authorized to perform this action.',
            );
    }

    public function test_removed_member_cannot_view_an_organization(): void
    {
        $user = User::factory()->create();

        $organization = $this->createOrganization('removed-member');

        $this->addMembership(
            $user,
            $organization,
            OrganizationMemberStatus::REMOVED,
        );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/organizations/'.$organization->public_id,
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'You are not authorized to perform this action.',
            );
    }

    public function test_organization_detail_requires_authentication(): void
    {
        $organization = $this->createOrganization('authentication');

        $this->getJson(
            '/api/v1/organizations/'.$organization->public_id,
        )->assertUnauthorized();
    }

    public function test_unknown_or_numeric_organization_identifiers_return_not_found(): void
    {
        $user = User::factory()->create();

        $organization = $this->createOrganization('numeric-id');

        $this->addMembership($user, $organization);

        $this->actingAs($user)
            ->getJson(
                '/api/v1/organizations/org_01J00000000000000000000000',
            )
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Resource not found.',
            );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/organizations/'.$organization->id,
            )
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Resource not found.',
            );
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
        OrganizationMemberStatus $status = OrganizationMemberStatus::ACTIVE,
    ): OrganizationMember {
        return OrganizationMember::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => $status->value,
        ]);
    }
}
