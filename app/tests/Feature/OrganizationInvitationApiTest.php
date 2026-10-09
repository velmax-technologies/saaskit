<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_invitation_and_only_token_hash_is_stored(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $response = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/organizations/{$organization->public_id}/invitations",
            [
                'email' => 'Invitee@Example.com',
                'role' => Organization::ROLE_MEMBER,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invitation.email', 'invitee@example.com')
            ->assertJsonPath('data.invitation.role', Organization::ROLE_MEMBER)
            ->assertJsonPath(
                'data.invitation.id',
                fn ($id) => is_string($id) && str_starts_with($id, 'inv_'),
            )
            ->assertJsonMissingPath('data.invitation.token_hash')
            ->assertJsonMissingPath('data.invitation.database_id');

        $invitation = OrganizationInvitation::query()
            ->where('public_id', $response->json('data.invitation.id'))
            ->firstOrFail();

        $this->assertSame(
            'invitee@example.com',
            $invitation->email,
        );

        $this->assertTrue($invitation->expires_at->isFuture());

        $rawToken = null;

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (
                OrganizationInvitationNotification $notification,
            ) use (&$rawToken): bool {
                $rawToken = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($rawToken);
        $this->assertSame(
            hash('sha256', $rawToken),
            $invitation->token_hash,
        );
        $this->assertStringNotContainsString(
            $rawToken,
            $response->getContent(),
        );
    }

    public function test_non_admin_member_cannot_manage_invitations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $organization->users()->attach($member->id, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                ['email' => 'invitee@example.com'],
            )
            ->assertForbidden();

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}/invitations",
        )->assertForbidden();
    }

    public function test_owner_can_list_invitations_without_exposing_token_hash(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
        );

        $this->actingAs($owner, 'sanctum')
            ->getJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.invitations.0.id',
                $invitation->public_id,
            )
            ->assertJsonMissingPath('data.invitations.0.token_hash');
    }

    public function test_duplicate_pending_invitation_is_rejected(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
        );

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                ['email' => 'INVITEE@example.com'],
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(1, $organization->invitations()->count());
    }

    public function test_existing_organization_member_cannot_be_invited_again(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $organization->users()->attach($member->id, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations",
                ['email' => $member->email],
            )
            ->assertUnprocessable();

        $this->assertSame(0, $organization->invitations()->count());
    }

    public function test_owner_can_revoke_pending_invitation(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
        );

        $this->actingAs($owner, 'sanctum')
            ->deleteJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}",
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_invitation_can_be_accepted_by_matching_email(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $token = 'valid-invitation-token-for-testing';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            $invitee->email,
            Organization::ROLE_ADMIN,
            $token,
        );

        $this->actingAs($invitee, 'sanctum')
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization.id', $organization->public_id)
            ->assertJsonPath('data.role', Organization::ROLE_ADMIN);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
            'role' => Organization::ROLE_ADMIN,
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_invitation_acceptance_rejects_invalid_token(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $invitation = $this->createInvitation(
            $organization,
            $owner,
            $invitee->email,
            Organization::ROLE_MEMBER,
            'correct-invitation-token',
        );

        $this->actingAs($invitee, 'sanctum')
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => 'incorrect-invitation-token-with-enough-length'],
            )
            ->assertUnprocessable();

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_invitation_acceptance_rejects_different_email(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'another@example.com',
        ]);
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $token = 'valid-invitation-token-for-testing';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
            Organization::ROLE_MEMBER,
            $token,
        );

        $this->actingAs($invitee, 'sanctum')
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            )
            ->assertForbidden();

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $token = 'valid-invitation-token-for-testing';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            $invitee->email,
            Organization::ROLE_MEMBER,
            $token,
        );

        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($invitee, 'sanctum')
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            )
            ->assertUnprocessable();

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $token = 'valid-invitation-token-for-testing';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            $invitee->email,
            Organization::ROLE_MEMBER,
            $token,
        );

        $invitation->update(['revoked_at' => now()]);

        $this->actingAs($invitee, 'sanctum')
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            )
            ->assertUnprocessable();

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_accepted_invitation_cannot_be_accepted_again(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $token = 'valid-invitation-token-for-testing';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            $invitee->email,
            Organization::ROLE_MEMBER,
            $token,
        );

        $invitation->update(['accepted_at' => now()]);

        $this->actingAs($invitee, 'sanctum')
            ->postJson(
                "/api/v1/invitations/{$invitation->public_id}/accept",
                ['token' => $token],
            )
            ->assertUnprocessable();
    }

    public function test_acceptance_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole(
            $owner,
            Organization::ROLE_OWNER,
        );

        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
            Organization::ROLE_MEMBER,
            'valid-invitation-token-for-testing',
        );

        $this->postJson(
            "/api/v1/invitations/{$invitation->public_id}/accept",
            ['token' => 'valid-invitation-token-for-testing'],
        )->assertUnauthorized();
    }

    public function test_owner_can_resend_invitation_and_rotate_its_token(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole($owner, Organization::ROLE_OWNER);
        $oldToken = 'previous-valid-invitation-token';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
            Organization::ROLE_MEMBER,
            $oldToken,
        );
        $oldHash = $invitation->token_hash;

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend")
            ->assertOk()
            ->assertJsonMissingPath('data.invitation.token_hash');

        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->isFuture());

        $newToken = null;
        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification) use (&$newToken): bool {
                $newToken = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $invitation->token_hash);
    }

    public function test_resend_reports_delivery_failure_and_keeps_invitation_pending(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole($owner, Organization::ROLE_OWNER);
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
        );
        Notification::swap(new class
        {
            public function route(...$arguments): never
            {
                throw new \RuntimeException('Simulated mail transport failure');
            }
        });
        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertServiceUnavailable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'accepted_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function test_regular_member_cannot_resend_invitation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->createOrganizationWithRole($owner, Organization::ROLE_OWNER);
        $organization->users()->attach($member->id, ['role' => Organization::ROLE_MEMBER]);
        $invitation = $this->createInvitation($organization, $owner, 'invitee@example.com');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend")
            ->assertForbidden();
    }

    public function test_accepted_invitation_cannot_be_resent(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole($owner, Organization::ROLE_OWNER);
        $invitation = $this->createInvitation($organization, $owner, 'invitee@example.com');
        $invitation->update(['accepted_at' => now()]);
        $originalHash = $invitation->token_hash;

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend")
            ->assertUnprocessable();

        $this->assertSame($originalHash, $invitation->fresh()->token_hash);
        Notification::assertNothingSent();
    }

    public function test_revoked_invitation_cannot_be_resent(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole($owner, Organization::ROLE_OWNER);
        $invitation = $this->createInvitation($organization, $owner, 'invitee@example.com');
        $invitation->update(['revoked_at' => now()]);
        $originalHash = $invitation->token_hash;

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend")
            ->assertUnprocessable();

        $this->assertSame($originalHash, $invitation->fresh()->token_hash);
        Notification::assertNothingSent();
    }

    public function test_expired_invitation_can_be_resent_with_a_new_token_and_expiry(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithRole($owner, Organization::ROLE_OWNER);
        $oldToken = 'expired-invitation-token';
        $invitation = $this->createInvitation(
            $organization,
            $owner,
            'invitee@example.com',
            Organization::ROLE_MEMBER,
            $oldToken,
        );
        $invitation->update(['expires_at' => now()->subMinute()]);
        $oldHash = $invitation->token_hash;

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend")
            ->assertOk();

        $invitation->refresh();

        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->isFuture());

        $newToken = null;
        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification) use (&$newToken): bool {
                $newToken = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($newToken);
        $this->assertSame(hash('sha256', $newToken), $invitation->token_hash);
        $this->assertNotSame($oldToken, $newToken);
    }

    private function createOrganizationWithRole(
        User $user,
        string $role,
    ): Organization {
        $organization = Organization::factory()->create();

        $organization->users()->attach($user->id, [
            'role' => $role,
        ]);

        return $organization;
    }

    private function createInvitation(
        Organization $organization,
        User $inviter,
        string $email,
        string $role = Organization::ROLE_MEMBER,
        string $token = 'test-invitation-token',
    ): OrganizationInvitation {
        return $organization->invitations()->create([
            'invited_by' => $inviter->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'role' => $role,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
