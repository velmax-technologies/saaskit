<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationInvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function createInvitation(
        User $invitedUser,
        Organization $organization,
        string $token = 'test-invitation-token',
        OrganizationMemberRole $role = OrganizationMemberRole::MEMBER,
    ): OrganizationInvitation {
        return OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $organization->members()
                ->where('role', OrganizationMemberRole::OWNER->value)
                ->first()
                ?->user_id,
            'email' => $invitedUser->email,
            'role' => $role->value,
            'token_hash' => hash('sha256', $token),
            'status' => OrganizationInvitationStatus::PENDING->value,
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function createOwner(
        Organization $organization,
        ?User $user = null,
    ): User {
        $user ??= User::factory()->create();

        OrganizationMember::factory()->owner()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);

        return $user;
    }

    public function test_authenticated_user_can_accept_valid_invitation(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);
        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.membership.user.id',
                $invitedUser->public_id,
            )
            ->assertJsonPath(
                'data.membership.organization.id',
                $organization->public_id,
            )
            ->assertJsonPath(
                'data.membership.role',
                OrganizationMemberRole::MEMBER->value,
            )
            ->assertJsonPath(
                'data.membership.status',
                'active',
            );

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'status' => OrganizationInvitationStatus::ACCEPTED->value,
        ]);

        $this->assertNotNull(
            $invitation->fresh()->accepted_at,
        );
    }

    public function test_admin_invitation_creates_admin_membership(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
            role: OrganizationMemberRole::ADMIN,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.membership.role',
                OrganizationMemberRole::ADMIN->value,
            );

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::ADMIN->value,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_user_cannot_accept_invitation(): void
    {
        $organization = Organization::factory()->create();

        $owner = $this->createOwner($organization);
        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->postJson(
            "/api/v1/invitations/{$invitation->public_id}/accept",
            ['token' => $token],
        );

        $response->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);
        $invalidToken = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $invalidToken],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'status' => OrganizationInvitationStatus::PENDING->value,
        ]);

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_expired_invitation_is_rejected_and_marked_expired(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $invitation->update([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'status' => OrganizationInvitationStatus::EXPIRED->value,
        ]);

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_accepted_invitation_cannot_be_accepted_again(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $invitation->update([
            'status' => OrganizationInvitationStatus::ACCEPTED->value,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_cancelled_invitation_cannot_be_accepted(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $invitation->update([
            'status' => OrganizationInvitationStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_user_with_different_email_cannot_accept_invitation(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create([
            'email' => 'invited@example.com',
        ]);

        $differentUser = User::factory()->create([
            'email' => 'different@example.com',
        ]);

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($differentUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $differentUser->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_existing_membership_prevents_acceptance(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        OrganizationMember::factory()->member()->create([
            'user_id' => $invitedUser->id,
            'organization_id' => $organization->id,
        ]);

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);

        $this->assertSame(
            1,
            OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $invitedUser->id)
                ->count(),
        );

        $this->assertSame(
            OrganizationInvitationStatus::PENDING,
            $invitation->fresh()->status,
        );
    }

    public function test_missing_token_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_invalid_token_length_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => 'short-token'],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_numeric_invitation_id_does_not_bind(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->id}/accept",
                ['token' => $token],
            );

        $response->assertNotFound();
    }

    public function test_token_hash_is_never_exposed_in_response(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.membership.token')
            ->assertJsonMissingPath('data.membership.token_hash');
    }

    public function test_invitation_token_is_stored_as_a_hash(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $this->assertSame(
            hash('sha256', $token),
            $invitation->token_hash,
        );

        $this->assertNotSame(
            $token,
            $invitation->token_hash,
        );
    }

    public function test_invitation_route_uses_public_id(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response->assertOk();

        $membership = OrganizationMember::query()
            ->where('user_id', $invitedUser->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertStringStartsWith('mem_', $membership->public_id);
        $this->assertStringStartsWith('inv_', $invitation->public_id);
        $response->assertJsonPath(
            'data.membership.id',
            $membership->public_id,
        );

    }

    public function test_left_member_can_accept_a_new_invitation(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $membership = OrganizationMember::factory()
            ->member()
            ->left()
            ->create([
                'user_id' => $invitedUser->id,
                'organization_id' => $organization->id,
            ]);

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.membership.id',
                $membership->public_id,
            );

        $membership = $membership->fresh();

        $this->assertSame(
            OrganizationMemberStatus::ACTIVE,
            $membership->status,
        );

        $this->assertSame(
            OrganizationMemberRole::MEMBER,
            $membership->role,
        );

        $this->assertSame(
            1,
            OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $invitedUser->id)
                ->count(),
        );

        $this->assertSame(
            OrganizationInvitationStatus::ACCEPTED,
            $invitation->fresh()->status,
        );
    }

    public function test_removed_member_can_accept_a_new_invitation(): void
    {
        $organization = Organization::factory()->create();

        $this->createOwner($organization);

        $invitedUser = User::factory()->create();

        $membership = OrganizationMember::factory()
            ->member()
            ->removed()
            ->create([
                'user_id' => $invitedUser->id,
                'organization_id' => $organization->id,
            ]);

        $token = Str::random(64);

        $invitation = $this->createInvitation(
            invitedUser: $invitedUser,
            organization: $organization,
            token: $token,
        );

        $response = $this->actingAs($invitedUser)
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.membership.id',
                $membership->public_id,
            );

        $membership = $membership->fresh();

        $this->assertSame(
            OrganizationMemberStatus::ACTIVE,
            $membership->status,
        );

        $this->assertSame(
            OrganizationMemberRole::MEMBER,
            $membership->role,
        );

        $this->assertSame(
            1,
            OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $invitedUser->id)
                ->count(),
        );

        $this->assertSame(
            OrganizationInvitationStatus::ACCEPTED,
            $invitation->fresh()->status,
        );
    }
}
