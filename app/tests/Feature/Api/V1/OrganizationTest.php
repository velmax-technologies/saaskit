<?php

namespace Tests\Feature\Api\V1;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_require_authentication(): void
    {
        $this->getJson('/api/v1/organizations')
            ->assertUnauthorized();

        $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
        ])->assertUnauthorized();
    }

    public function test_created_organization_exposes_public_id_and_not_database_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Public ID Test Organization',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.organization.id', fn ($id) => is_string($id) && str_starts_with($id, 'org_'))
            ->assertJsonMissingPath('data.organization.database_id');

        $organization = Organization::query()
            ->where('name', 'Public ID Test Organization')
            ->firstOrFail();

        $response->assertJsonPath(
            'data.organization.id',
            $organization->public_id
        );

        $this->assertNotSame(
            (string) $organization->id,
            $response->json('data.organization.id')
        );
    }

    public function test_organization_list_exposes_public_id_and_not_database_id(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $user->organizations()->attach($organization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations');

        $response->assertOk()
            ->assertJsonPath(
                'data.organizations.0.id',
                $organization->public_id
            )
            ->assertJsonMissingPath('data.organizations.0.database_id');

        $this->assertNotSame(
            (string) $organization->id,
            $response->json('data.organizations.0.id')
        );
    }

    public function test_authenticated_user_can_create_an_organization(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization.name', 'Acme Inc.')
            ->assertJsonPath('data.organization.slug', 'acme-inc')
            ->assertJsonPath('data.role', Organization::ROLE_OWNER);

        $organization = Organization::where('slug', 'acme-inc')->firstOrFail();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => Organization::ROLE_OWNER,
        ]);
    }

    public function test_authenticated_user_can_create_an_organization_with_custom_slug(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
            'slug' => 'acme-network',
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization.slug', 'acme-network');
    }

    public function test_organization_creation_validates_required_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_organization_slug_must_be_unique(): void
    {
        Organization::factory()->create([
            'slug' => 'acme-network',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [
            'name' => 'Another Acme',
            'slug' => 'acme-network',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_organization_members_expose_public_ids(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        $organization->users()->attach([
            $user->id => [
                'role' => Organization::ROLE_OWNER,
            ],
            $member->id => [
                'role' => Organization::ROLE_MEMBER,
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/v1/organizations/'.$organization->public_id.'/members',
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.members');

        $members = $response->json('data.members');

        foreach ($members as $membership) {
            $this->assertIsString($membership['id']);
            $this->assertStringStartsWith('mem_', $membership['id']);

            $this->assertArrayNotHasKey('database_id', $membership);
            $this->assertArrayNotHasKey('organization_id', $membership);
            $this->assertArrayNotHasKey('user_id', $membership);

            $this->assertArrayHasKey('user', $membership);
            $this->assertStringStartsWith('usr_', $membership['user']['id']);

            $this->assertArrayNotHasKey(
                'database_id',
                $membership['user'],
            );
        }

        $response
            ->assertJsonMissingPath('data.members.0.organization_id')
            ->assertJsonMissingPath('data.members.0.user_id');
    }

    public function test_owner_can_add_an_organization_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/members",
                [
                    'user_id' => $member->public_id,
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.user.id', $member->public_id)
            ->assertJsonPath('data.member.organization.id', $organization->public_id)
            ->assertJsonPath('data.member.role', Organization::ROLE_MEMBER)
            ->assertJsonMissingPath('data.member.database_id')
            ->assertJsonMissingPath('data.member.organization_id')
            ->assertJsonMissingPath('data.member.user_id');

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertStringStartsWith('mem_', $membership->public_id);
    }

    public function test_admin_can_add_an_organization_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($admin, [
            'role' => Organization::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/members",
                [
                    'user_id' => $member->public_id,
                    'role' => Organization::ROLE_ADMIN,
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath('data.member.user.id', $member->public_id)
            ->assertJsonPath('data.member.role', Organization::ROLE_ADMIN);
    }

    public function test_regular_member_cannot_add_an_organization_member(): void
    {
        $existingMember = User::factory()->create();
        $newMember = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($existingMember, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $response = $this->actingAs($existingMember, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/members",
                [
                    'user_id' => $newMember->public_id,
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $newMember->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_add_an_organization_member(): void
    {
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/members",
            [
                'user_id' => $member->public_id,
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_adding_nonexistent_public_user_id_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/members",
                [
                    'user_id' => 'usr_01INVALIDUSERID000000000000',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_numeric_user_id_cannot_be_used_to_add_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/members",
                [
                    'user_id' => (string) $member->id,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_duplicate_organization_membership_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/members",
                [
                    'user_id' => $member->public_id,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'User is already a member of this organization.',
            );

        $this->assertSame(
            2,
            Membership::query()
                ->where('organization_id', $organization->id)
                ->count(),
        );
    }

    public function test_organization_members_require_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $organization = Organization::factory()->create();

        $organization->users()->attach($otherUser, [
            'role' => Organization::ROLE_OWNER,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/organizations/'.$organization->public_id.'/members',
        )->assertNotFound();
    }

    public function test_organization_members_require_authentication(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson(
            '/api/v1/organizations/'.$organization->public_id.'/members',
        )->assertUnauthorized();
    }

    public function test_organization_members_use_public_organization_id(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_OWNER,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/v1/organizations/'.$organization->public_id.'/members',
        );

        $response->assertOk();

        $numericRoute = '/api/v1/organizations/'.$organization->id.'/members';

        $this->getJson($numericRoute)
            ->assertNotFound();
    }

    public function test_user_can_only_list_organizations_they_belong_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $organization = Organization::factory()->create([
            'name' => 'My Organization',
            'slug' => 'my-organization',
        ]);

        $otherOrganization = Organization::factory()->create([
            'name' => 'Other Organization',
            'slug' => 'other-organization',
        ]);

        $user->organizations()->attach($organization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $otherUser->organizations()->attach($otherOrganization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'name' => 'My Organization',
                'slug' => 'my-organization',
            ])
            ->assertJsonMissing([
                'name' => 'Other Organization',
                'slug' => 'other-organization',
            ]);
    }

    public function test_owner_can_update_member_role_using_public_membership_id(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);
        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}",
                ['role' => Organization::ROLE_ADMIN],
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.id', $membership->public_id)
            ->assertJsonPath('data.member.role', Organization::ROLE_ADMIN)
            ->assertJsonMissingPath('data.member.database_id');

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => Organization::ROLE_ADMIN,
        ]);
    }

    public function test_admin_can_update_regular_member_role(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($admin, [
            'role' => Organization::ROLE_ADMIN,
        ]);
        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}",
                ['role' => Organization::ROLE_ADMIN],
            )
            ->assertOk()
            ->assertJsonPath('data.member.role', Organization::ROLE_ADMIN);
    }

    public function test_regular_member_cannot_update_membership_role(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($actor, [
            'role' => Organization::ROLE_MEMBER,
        ]);
        $organization->users()->attach($target, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}",
                ['role' => Organization::ROLE_ADMIN],
            )
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $target->id,
            'role' => Organization::ROLE_MEMBER,
        ]);
    }

    public function test_owner_role_cannot_be_assigned_through_member_role_endpoint(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);
        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}",
                ['role' => Organization::ROLE_OWNER],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_membership_from_another_organization_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);
        $otherOrganization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = Membership::query()
            ->where('organization_id', $otherOrganization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}",
                ['role' => Organization::ROLE_ADMIN],
            )
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $otherOrganization->id,
            'user_id' => $member->id,
            'role' => Organization::ROLE_MEMBER,
        ]);
    }

    public function test_owner_can_update_organization_using_public_id(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->public_id}", [
                'name' => 'New Name',
                'slug' => 'new-name',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization.id', $organization->public_id)
            ->assertJsonPath('data.organization.name', 'New Name')
            ->assertJsonPath('data.organization.slug', 'new-name')
            ->assertJsonMissingPath('data.organization.database_id');

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);
    }

    public function test_owner_can_update_organization_name_without_changing_slug(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'name' => 'Old Name',
            'slug' => 'keep-this-slug',
        ]);

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->public_id}", [
                'name' => 'Updated Name',
            ])
            ->assertOk()
            ->assertJsonPath('data.organization.name', 'Updated Name')
            ->assertJsonPath('data.organization.slug', 'keep-this-slug');
    }

    public function test_admin_cannot_update_organization_settings(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($admin, [
            'role' => Organization::ROLE_ADMIN,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->public_id}", [
                'name' => 'Unauthorized Change',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => $organization->name,
        ]);
    }

    public function test_non_member_cannot_update_organization_settings(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->public_id}", [
                'name' => 'Unauthorized Change',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => $organization->name,
        ]);
    }

    public function test_organization_update_rejects_duplicate_slug(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'slug' => 'my-organization',
        ]);
        Organization::factory()->create(['slug' => 'taken-slug']);

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->public_id}", [
                'slug' => 'taken-slug',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_organization_update_rejects_invalid_slug(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->public_id}", [
                'slug' => 'invalid slug!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_owner_can_delete_organization_using_public_id(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);
        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $organizationId = $organization->id;

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Organization deleted successfully.');

        $this->assertDatabaseMissing('organizations', [
            'id' => $organizationId,
        ]);

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organizationId,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
        ]);
    }

    public function test_admin_cannot_delete_organization(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($admin, [
            'role' => Organization::ROLE_ADMIN,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
        ]);
    }

    public function test_non_member_cannot_delete_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
        ]);
    }

    public function test_organization_deletion_requires_authentication(): void
    {
        $organization = Organization::factory()->create();

        $this->deleteJson("/api/v1/organizations/{$organization->public_id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
        ]);
    }

    public function test_owner_can_transfer_organization_ownership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);
        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $targetMembership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/ownership",
                ['membership_id' => $targetMembership->public_id],
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Organization ownership transferred successfully.')
            ->assertJsonPath('data.previous_owner.role', Organization::ROLE_ADMIN)
            ->assertJsonPath('data.previous_owner.user.id', $owner->public_id)
            ->assertJsonPath('data.new_owner.role', Organization::ROLE_OWNER)
            ->assertJsonPath('data.new_owner.user.id', $member->public_id)
            ->assertJsonMissingPath('data.new_owner.database_id');

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => Organization::ROLE_ADMIN,
        ]);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => Organization::ROLE_OWNER,
        ]);
    }

    public function test_admin_cannot_transfer_organization_ownership(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($admin, [
            'role' => Organization::ROLE_ADMIN,
        ]);
        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $targetMembership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/ownership",
                ['membership_id' => $targetMembership->public_id],
            )
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => Organization::ROLE_ADMIN,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => Organization::ROLE_MEMBER,
        ]);
    }

    public function test_regular_member_cannot_transfer_organization_ownership(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($member, [
            'role' => Organization::ROLE_MEMBER,
        ]);
        $organization->users()->attach($target, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $targetMembership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $this->actingAs($member, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/ownership",
                ['membership_id' => $targetMembership->public_id],
            )
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => Organization::ROLE_MEMBER,
        ]);
    }

    public function test_unauthenticated_user_cannot_transfer_organization_ownership(): void
    {
        $organization = Organization::factory()->create();

        $this->patchJson(
            "/api/v1/organizations/{$organization->public_id}/ownership",
            ['membership_id' => 'mem_nonexistent'],
        )->assertUnauthorized();
    }

    public function test_ownership_transfer_rejects_membership_from_another_organization(): void
    {
        $owner = User::factory()->create();
        $otherMember = User::factory()->create();
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);
        $otherOrganization->users()->attach($otherMember, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $targetMembership = Membership::query()
            ->where('organization_id', $otherOrganization->id)
            ->where('user_id', $otherMember->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/ownership",
                ['membership_id' => $targetMembership->public_id],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['membership_id']);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => Organization::ROLE_OWNER,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherMember->id,
            'role' => Organization::ROLE_MEMBER,
        ]);
    }

    public function test_owner_cannot_transfer_ownership_to_themselves(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $ownerMembership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/ownership",
                ['membership_id' => $ownerMembership->public_id],
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => Organization::ROLE_OWNER,
        ]);
    }

    public function test_ownership_transfer_requires_a_valid_membership_public_id(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/organizations/{$organization->public_id}/ownership",
                ['membership_id' => '123'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['membership_id']);
    }

    public function test_owner_can_remove_member_using_public_membership_id(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, ['role' => Organization::ROLE_OWNER]);
        $organization->users()->attach($member, ['role' => Organization::ROLE_MEMBER]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('organization_user', ['id' => $membership->id]);
    }

    public function test_admin_can_remove_regular_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($admin, ['role' => Organization::ROLE_ADMIN]);
        $organization->users()->attach($member, ['role' => Organization::ROLE_MEMBER]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}")
            ->assertOk();

        $this->assertDatabaseMissing('organization_user', ['id' => $membership->id]);
    }

    public function test_regular_member_cannot_remove_another_member(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($actor, ['role' => Organization::ROLE_MEMBER]);
        $organization->users()->attach($target, ['role' => Organization::ROLE_MEMBER]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', ['id' => $membership->id]);
    }

    public function test_owner_membership_cannot_be_removed_through_member_endpoint(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner, ['role' => Organization::ROLE_OWNER]);

        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', ['id' => $membership->id]);
    }

    public function test_membership_from_another_organization_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $organization->users()->attach($owner, ['role' => Organization::ROLE_OWNER]);
        $otherOrganization->users()->attach($member, ['role' => Organization::ROLE_MEMBER]);

        $membership = Membership::query()
            ->where('organization_id', $otherOrganization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->public_id}/members/{$membership->public_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('organization_user', ['id' => $membership->id]);
    }
}
