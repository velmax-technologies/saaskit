<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationInvitationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_an_invitation(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'newuser@example.com',
                    'role' => 'member',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.invitation.email',
                'newuser@example.com',
            )
            ->assertJsonPath(
                'data.invitation.role',
                'member',
            )
            ->assertJsonPath(
                'data.invitation.status',
                'pending',
            );

        $response->assertJsonMissing([
            'token_hash',
        ]);

        $response->assertJsonMissing([
            'token',
        ]);

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_create_an_invitation(): void
    {
        $admin = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $admin->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'admin-invite@example.com',
                    'role' => 'admin',
                ],
            );

        $response->assertCreated();

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'admin-invite@example.com',
            'role' => 'admin',
            'status' => 'pending',
        ]);
    }

    public function test_member_cannot_create_an_invitation(): void
    {
        $member = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $member->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($member, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'newuser@example.com',
                    'role' => 'member',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'organization_invitations',
            0,
        );
    }

    public function test_unauthenticated_user_cannot_create_an_invitation(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/invitations",
            [
                'email' => 'newuser@example.com',
                'role' => 'member',
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_invitation_requires_valid_email(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'not-an-email',
                    'role' => 'member',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_invitation_requires_a_valid_role(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'newuser@example.com',
                    'role' => 'owner',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);
    }

    public function test_invitation_email_is_normalized(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => '  NewUser@Example.COM  ',
                    'role' => 'member',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.invitation.email',
                'newuser@example.com',
            );

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_existing_active_member_cannot_be_invited(): void
    {
        $owner = User::factory()->create();

        $member = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'user_id' => $member->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'existing@example.com',
                    'role' => 'member',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_duplicate_pending_invitation_is_rejected(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'pending@example.com',
            'role' => OrganizationMemberRole::MEMBER->value,
            'token_hash' => hash(
                'sha256',
                'existing-secret-token',
            ),
            'status' => OrganizationInvitationStatus::PENDING->value,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'pending@example.com',
                    'role' => 'member',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertDatabaseCount(
            'organization_invitations',
            1,
        );
    }

    public function test_expired_pending_invitation_does_not_block_new_invitation(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'expired@example.com',
            'role' => OrganizationMemberRole::MEMBER->value,
            'token_hash' => hash(
                'sha256',
                'expired-secret-token',
            ),
            'status' => OrganizationInvitationStatus::PENDING->value,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'expired@example.com',
                    'role' => 'member',
                ],
            );

        $response->assertCreated();

        $this->assertDatabaseCount(
            'organization_invitations',
            2,
        );
    }

    public function test_invitation_token_is_hashed(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'secure@example.com',
                    'role' => 'member',
                ],
            );

        $response->assertCreated();

        $invitation = OrganizationInvitation::query()->first();

        $this->assertNotNull($invitation);
        $this->assertNotEmpty($invitation->token_hash);
        $this->assertSame(64, strlen($invitation->token_hash));

        $response->assertJsonMissing([
            'token' => $invitation->token_hash,
        ]);

        $response->assertJsonMissing([
            'token_hash' => $invitation->token_hash,
        ]);
    }

    public function test_invitation_has_public_id_and_does_not_expose_numeric_id(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'public-id@example.com',
                    'role' => 'member',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'invitation' => [
                        'id',
                        'email',
                        'role',
                        'status',
                        'expires_at',
                    ],
                ],
            ]);

        $invitation = OrganizationInvitation::query()->first();

        $this->assertNotNull($invitation);

        $this->assertStringStartsWith(
            'inv_',
            $invitation->public_id,
        );

        $response->assertJsonPath(
            'data.invitation.id',
            $invitation->public_id,
        );

        $response->assertJsonMissingPath(
            'data.invitation.numeric_id',
        );
    }

    public function test_invitation_uses_configured_expiration(): void
    {
        config([
            'saaskit.organization.invitation_expire' => 14,
        ]);

        $owner = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);

        $before = now()->addDays(14)->subSeconds(2);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                [
                    'email' => 'expiration@example.com',
                    'role' => 'member',
                ],
            );

        $response->assertCreated();

        $invitation = OrganizationInvitation::query()->first();

        $this->assertNotNull($invitation);

        $this->assertTrue(
            $invitation->expires_at->greaterThan($before),
        );

        $this->assertTrue(
            $invitation->expires_at->lessThanOrEqualTo(
                now()->addDays(14),
            ),
        );
    }
}
