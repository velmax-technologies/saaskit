<?php

namespace Tests\Feature\Api\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationRejoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_member_can_rejoin_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.member.id',
                $member->public_id,
            )
            ->assertJsonPath(
                'data.member.role',
                'member',
            )
            ->assertJsonPath(
                'data.member.status',
                'active',
            );

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'status' => 'active',
        ]);
    }

    public function test_inactive_admin_can_rejoin_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->admin()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.member.id',
                $member->public_id,
            )
            ->assertJsonPath(
                'data.member.role',
                'admin',
            )
            ->assertJsonPath(
                'data.member.status',
                'active',
            );
    }

    public function test_rejoining_does_not_create_duplicate_membership(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            )
            ->assertSuccessful();

        $this->assertDatabaseCount('organization_members', 1);

        $this->assertDatabaseHas('organization_members', [
            'id' => $member->id,
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_rejoining_preserves_membership_public_id(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            )
            ->assertSuccessful()
            ->assertJsonPath(
                'data.member.id',
                $member->public_id,
            );
    }

    public function test_rejoining_preserves_membership_role(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->admin()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            )
            ->assertSuccessful()
            ->assertJsonPath(
                'data.member.role',
                'admin',
            );
    }

    public function test_active_member_cannot_rejoin_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'organization',
            ]);
    }

    public function test_non_member_cannot_rejoin_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            )
            ->assertNotFound();
    }

    public function test_member_of_another_organization_cannot_rejoin_this_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $otherOrganization->id,
            ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            )
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_rejoin_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/rejoin",
        )->assertUnauthorized();
    }

    public function test_rejoining_does_not_expose_numeric_ids(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $member = OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($user)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/rejoin",
            );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.member.id',
                $member->public_id,
            )
            ->assertJsonMissing([
                'id' => $member->id,
            ])
            ->assertJsonMissing([
                'user_id' => $user->id,
            ])
            ->assertJsonMissing([
                'organization_id' => $organization->id,
            ]);
    }
}
