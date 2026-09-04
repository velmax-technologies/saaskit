<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInvitationListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_organization_invitations(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()
            ->count(3)
            ->create([
                'organization_id' => $organization->id,
                'invited_by' => $owner->id,
            ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'invitations' => [
                        '*' => [
                            'id',
                            'email',
                            'role',
                            'status',
                            'expires_at',
                            'accepted_at',
                            'cancelled_at',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
                'meta',
                'links',
            ])
            ->assertJsonCount(3, 'data.invitations');
    }

    public function test_admin_can_list_organization_invitations(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $admin->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()
            ->count(2)
            ->create([
                'organization_id' => $organization->id,
                'invited_by' => $admin->id,
            ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.invitations');
    }

    public function test_member_can_list_organization_invitations(): void
    {
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $member->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()
            ->count(2)
            ->create([
                'organization_id' => $organization->id,
                'invited_by' => $member->id,
            ]);

        $response = $this->actingAs($member)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.invitations');
    }

    public function test_unauthenticated_user_cannot_list_invitations(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/invitations",
        );

        $response->assertUnauthorized();
    }

    public function test_user_from_another_organization_cannot_list_invitations(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationInvitation::factory()
            ->count(2)
            ->create([
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response->assertForbidden();
    }

    public function test_inactive_member_cannot_list_invitations(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response->assertForbidden();
    }

    public function test_invitations_are_isolated_between_organizations(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()
            ->count(2)
            ->create([
                'organization_id' => $organization->id,
            ]);

        OrganizationInvitation::factory()
            ->count(5)
            ->create([
                'organization_id' => $otherOrganization->id,
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.invitations');
    }

    public function test_invitations_can_be_filtered_by_status(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()
            ->pending()
            ->count(3)
            ->create([
                'organization_id' => $organization->id,
                'invited_by' => $owner->id,
            ]);

        OrganizationInvitation::factory()
            ->accepted()
            ->count(2)
            ->create([
                'organization_id' => $organization->id,
                'invited_by' => $owner->id,
            ]);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations?status=pending",
            );

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data.invitations');

        foreach ($response->json('data.invitations') as $invitation) {
            $this->assertSame(
                OrganizationInvitationStatus::PENDING->value,
                $invitation['status'],
            );
        }
    }

    public function test_invalid_status_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations?status=invalid",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_invitations_can_be_filtered_by_email(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'john@example.com',
        ]);

        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'jane@example.com',
        ]);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations?email=john@example.com",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.invitations')
            ->assertJsonPath(
                'data.invitations.0.email',
                'john@example.com',
            );
    }

    public function test_email_filter_is_normalized(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'john@example.com',
        ]);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations?email=%20JOHN%40EXAMPLE.COM%20",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.invitations');
    }

    public function test_pagination_is_supported(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()
            ->count(25)
            ->create([
                'organization_id' => $organization->id,
                'invited_by' => $owner->id,
            ]);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations?per_page=10",
            );

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data.invitations')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_per_page_is_limited_to_100(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations?per_page=101",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_public_invitation_id_is_returned_instead_of_numeric_id(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $invitation = $response->json('data.invitations.0');

        $this->assertStringStartsWith('inv_', $invitation['id']);
        $this->assertIsString($invitation['id']);
    }

    public function test_invitation_token_is_never_exposed(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.invitations.0.token')
            ->assertJsonMissingPath('data.invitations.0.token_hash')
            ->assertJsonMissingPath('data.invitations.0.id_numeric');
    }

    public function test_empty_organization_returns_empty_invitation_list(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organization->public_id}/invitations");

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data.invitations')
            ->assertJsonPath('meta.total', 0);
    }
}