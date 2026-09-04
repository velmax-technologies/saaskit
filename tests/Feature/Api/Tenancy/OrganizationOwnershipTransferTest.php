<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationOwnershipTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();

        $organization = Organization::factory()->create();

        $ownerMember = OrganizationMember::factory()
            ->owner()
            ->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $target->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.member.id',
                $targetMember->public_id,
            )
            ->assertJsonPath(
                'data.member.role',
                OrganizationMemberRole::OWNER->value,
            )
            ->assertJsonPath(
                'data.member.status',
                'active',
            );

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownerMember->id,
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organization_members', [
            'id' => $targetMember->id,
            'user_id' => $target->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_transfer_ownership(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->admin()
            ->create([
                'user_id' => $admin->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $target->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($admin)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response->assertForbidden();
    }

    public function test_member_cannot_transfer_ownership(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $member->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $target->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($member)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_transfer_ownership(): void
    {
        $organization = Organization::factory()->create();

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $organization->id,
            ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
            [
                'member' => $targetMember->public_id,
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_target_must_belong_to_organization(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'organization_id' => $otherOrganization->id,
            ]);

        $response = $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response->assertNotFound();
    }

    public function test_inactive_member_cannot_become_owner(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->inactive()
            ->create([
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('organization_members', [
            'id' => $targetMember->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'inactive',
        ]);
    }

    public function test_existing_owner_cannot_be_selected_as_target(): void
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
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $ownerMember->public_id,
                ],
            );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownerMember->id,
            'role' => OrganizationMemberRole::OWNER->value,
        ]);
    }

    public function test_transfer_leaves_exactly_one_owner(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $target->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response->assertSuccessful();

        $this->assertSame(
            1,
            OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('role', OrganizationMemberRole::OWNER->value)
                ->where('status', 'active')
                ->count(),
        );
    }

    public function test_transfer_does_not_expose_numeric_ids(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->owner()
            ->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
            ]);

        $targetMember = OrganizationMember::factory()
            ->member()
            ->create([
                'user_id' => $target->id,
                'organization_id' => $organization->id,
            ]);

        $response = $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/ownership/transfer",
                [
                    'member' => $targetMember->public_id,
                ],
            );

        $response
            ->assertSuccessful()
            ->assertJsonMissing([
                'id' => $targetMember->id,
            ]);
    }
}
