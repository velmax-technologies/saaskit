<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\Api\ApiAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_read_token_cannot_access_organizations(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner->getKey(), [
            'role' => Organization::ROLE_OWNER,
        ]);

        $token = $owner->createToken(
            'profile-only',
            [ApiAbility::PROFILE_READ],
        )->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/organizations')
            ->assertForbidden();
    }

    public function test_profile_read_token_cannot_accept_organization_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);

        $organization = Organization::factory()->create();

        $organization->users()->attach($owner->getKey(), [
            'role' => Organization::ROLE_OWNER,
        ]);

        $rawToken = str()->random(64);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->getKey(),
            'invited_by' => $owner->getKey(),
            'email' => $invitee->email,
            'token_hash' => hash('sha256', $rawToken),
            'role' => Organization::ROLE_MEMBER,
            'expires_at' => now()->addDays(7),
        ]);

        $accessToken = $invitee->createToken(
            'profile-only',
            [ApiAbility::PROFILE_READ],
        )->plainTextToken;

        $this->withToken($accessToken)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $rawToken],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->getKey(),
            'user_id' => $invitee->getKey(),
        ]);

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_token_with_organization_read_ability_can_list_organizations(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner->getKey(), [
            'role' => Organization::ROLE_OWNER,
        ]);

        $token = $owner->createToken(
            'organization-reader',
            [ApiAbility::PROFILE_READ, 'organizations:read'],
        )->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/organizations')
            ->assertOk();
    }

    public function test_token_without_invitation_accept_ability_cannot_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);

        $organization = Organization::factory()->create();

        $organization->users()->attach($owner->getKey(), [
            'role' => Organization::ROLE_OWNER,
        ]);

        $rawToken = str()->random(64);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->getKey(),
            'invited_by' => $owner->getKey(),
            'email' => $invitee->email,
            'token_hash' => hash('sha256', $rawToken),
            'role' => Organization::ROLE_MEMBER,
            'expires_at' => now()->addDays(7),
        ]);

        $accessToken = $invitee->createToken(
            'organization-reader',
            [ApiAbility::PROFILE_READ, 'organizations:read'],
        )->plainTextToken;

        $this->withToken($accessToken)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $rawToken],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->getKey(),
            'user_id' => $invitee->getKey(),
        ]);

        $this->assertNull($invitation->fresh()->accepted_at);
    }



    public function test_profile_read_token_cannot_create_organization(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            'profile-only',
            [ApiAbility::PROFILE_READ],
        )->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/organizations', [
                'name' => 'Unauthorized Workspace',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_organization_read_token_cannot_create_organization(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            'organization-reader',
            [
                ApiAbility::PROFILE_READ,
                ApiAbility::ORGANIZATIONS_READ,
            ],
        )->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/organizations', [
                'name' => 'Unauthorized Workspace',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_organization_read_token_cannot_manage_invitations(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($owner->getKey(), [
            'role' => Organization::ROLE_OWNER,
        ]);

        $token = $owner->createToken(
            'organization-reader',
            [
                ApiAbility::PROFILE_READ,
                ApiAbility::ORGANIZATIONS_READ,
            ],
        )->plainTextToken;

        $this->withToken($token)
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
            )
            ->assertForbidden();
    }

}
