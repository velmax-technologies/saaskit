<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_generates_prefixed_public_id(): void
    {
        $organization = Organization::factory()->create();
        $inviter = User::factory()->create();

        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
            'email' => 'invitee@example.com',
            'token_hash' => hash('sha256', 'test-token'),
            'role' => Organization::ROLE_MEMBER,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertNotNull($invitation->public_id);
        $this->assertStringStartsWith('inv_', $invitation->public_id);
        $this->assertSame('public_id', $invitation->getRouteKeyName());
        $this->assertSame($invitation->public_id, $invitation->getRouteKey());
        $this->assertNotSame((string) $invitation->id, $invitation->public_id);
    }

    public function test_invitation_relationships_resolve_correct_models(): void
    {
        $organization = Organization::factory()->create();
        $inviter = User::factory()->create();

        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
            'email' => 'invitee@example.com',
            'token_hash' => hash('sha256', 'test-token'),
            'role' => Organization::ROLE_ADMIN,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($invitation->organization->is($organization));
        $this->assertTrue($invitation->inviter->is($inviter));
        $this->assertInstanceOf(
            Carbon::class,
            $invitation->expires_at,
        );
    }
}
