<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationInvitationResendTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(Organization $organization): User
    {
        $owner = User::factory()->create();

        OrganizationMember::factory()->owner()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
        ]);

        return $owner;
    }

    private function createInvitation(
        Organization $organization,
        User $owner,
        string $email = 'invite@example.com',
    ): OrganizationInvitation {
        return OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => $email,
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => OrganizationInvitationStatus::PENDING->value,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_owner_can_resend_pending_invitation(): void
    {
        $organization = Organization::factory()->create();
        $owner = $this->createOwner($organization);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $owner,
        );

        $oldHash = $invitation->token_hash;
        $oldExpiry = $invitation->expires_at;

        $oldHash = $invitation->token_hash;
        $oldExpiry = now()->addDay();

        $invitation->update([
            'expires_at' => $oldExpiry,
        ]);

        $response = $this
            ->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.invitation.id',
                $invitation->public_id,
            );

        $updated = $invitation->fresh();

        $this->assertNotSame($oldHash, $updated->token_hash);
        $this->assertTrue($updated->expires_at->isAfter($oldExpiry));
        $this->assertSame(
            OrganizationInvitationStatus::PENDING,
            $updated->status,
        );
    }

    public function test_admin_can_resend_invitation(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            'user_id' => $admin->id,
            'organization_id' => $organization->id,
        ]);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $admin,
        );

        $this->actingAs($admin)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertOk();
    }

    public function test_member_cannot_resend_invitation(): void
    {
        $organization = Organization::factory()->create();

        $member = User::factory()->create();

        OrganizationMember::factory()->member()->create([
            'user_id' => $member->id,
            'organization_id' => $organization->id,
        ]);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $member,
        );

        $this->actingAs($member)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertForbidden();
    }

    public function test_expired_invitation_can_be_resent(): void
    {
        $organization = Organization::factory()->create();
        $owner = $this->createOwner($organization);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $owner,
        );

        $invitation->update([
            'status' => OrganizationInvitationStatus::EXPIRED->value,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.invitation.status',
                OrganizationInvitationStatus::PENDING->value,
            );

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'status' => OrganizationInvitationStatus::PENDING->value,
        ]);
    }

    public function test_accepted_invitation_cannot_be_resent(): void
    {
        $organization = Organization::factory()->create();
        $owner = $this->createOwner($organization);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $owner,
        );

        $invitation->update([
            'status' => OrganizationInvitationStatus::ACCEPTED->value,
            'accepted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);
    }

    public function test_cancelled_invitation_cannot_be_resent(): void
    {
        $organization = Organization::factory()->create();
        $owner = $this->createOwner($organization);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $owner,
        );

        $invitation->update([
            'status' => OrganizationInvitationStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);
    }

    public function test_cross_organization_invitation_cannot_be_resent(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $owner = $this->createOwner($organization);

        $otherOwner = $this->createOwner($otherOrganization);

        $invitation = $this->createInvitation(
            organization: $otherOrganization,
            owner: $otherOwner,
        );

        $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->public_id}/resend",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation']);
    }

    public function test_numeric_invitation_id_does_not_bind(): void
    {
        $organization = Organization::factory()->create();
        $owner = $this->createOwner($organization);

        $invitation = $this->createInvitation(
            organization: $organization,
            owner: $owner,
        );

        $this->actingAs($owner)
            ->postJson(
                "/api/v1/organizations/{$organization->public_id}/invitations/{$invitation->id}/resend",
            )
            ->assertNotFound();
    }
}
