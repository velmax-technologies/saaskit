<?php

namespace Tests\Unit\Models;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_generates_public_id(): void
    {
        $user = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Acme Inc.',
            'slug' => 'acme-inc',
        ]);

        OrganizationMember::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER,
            'status' => 'active',
        ]);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $user->id,
            'email' => 'invite@example.com',
            'role' => OrganizationMemberRole::MEMBER,
            'token_hash' => hash('sha256', 'test-token'),
            'status' => OrganizationInvitationStatus::PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertNotEmpty($invitation->public_id);
        $this->assertStringStartsWith(
            'inv_',
            $invitation->public_id,
        );
    }

    public function test_public_id_is_used_as_route_key(): void
    {
        $invitation = new OrganizationInvitation();

        $this->assertSame(
            'public_id',
            $invitation->getRouteKeyName(),
        );
    }

    public function test_sensitive_fields_are_hidden(): void
    {
        $invitation = new OrganizationInvitation([
            'token_hash' => hash('sha256', 'secret-token'),
        ]);

        $serialized = $invitation->toArray();

        $this->assertArrayNotHasKey('id', $serialized);
        $this->assertArrayNotHasKey('token_hash', $serialized);
    }

    public function test_pending_invitation_is_pending(): void
    {
        $invitation = new OrganizationInvitation([
            'status' => OrganizationInvitationStatus::PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($invitation->isPending());
        $this->assertFalse($invitation->isAccepted());
        $this->assertFalse($invitation->isCancelled());
    }

    public function test_accepted_invitation_is_accepted(): void
    {
        $invitation = new OrganizationInvitation([
            'status' => OrganizationInvitationStatus::ACCEPTED,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($invitation->isAccepted());
        $this->assertFalse($invitation->isPending());
    }

    public function test_cancelled_invitation_is_cancelled(): void
    {
        $invitation = new OrganizationInvitation([
            'status' => OrganizationInvitationStatus::CANCELLED,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($invitation->isCancelled());
        $this->assertFalse($invitation->isPending());
    }

    public function test_expired_invitation_is_detected_by_expiration_date(): void
    {
        $invitation = new OrganizationInvitation([
            'status' => OrganizationInvitationStatus::PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($invitation->isExpired());
    }

    public function test_expired_status_is_detected(): void
    {
        $invitation = new OrganizationInvitation([
            'status' => OrganizationInvitationStatus::EXPIRED,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($invitation->isExpired());
    }

    public function test_relationships_are_defined(): void
    {
        $invitation = new OrganizationInvitation();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $invitation->organization(),
        );

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $invitation->inviter(),
        );
    }
}
