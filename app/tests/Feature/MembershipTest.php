<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_generates_prefixed_public_id(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = Membership::query()->firstOrFail();

        $this->assertNotNull($membership->public_id);
        $this->assertStringStartsWith('mem_', $membership->public_id);
        $this->assertSame('public_id', $membership->getRouteKeyName());
        $this->assertSame($membership->public_id, $membership->getRouteKey());
        $this->assertNotSame(
            (string) $membership->id,
            $membership->public_id,
        );
    }

    public function test_membership_public_ids_are_unique(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach([
            $firstUser->id => [
                'role' => Organization::ROLE_MEMBER,
            ],
            $secondUser->id => [
                'role' => Organization::ROLE_ADMIN,
            ],
        ]);

        $memberships = Membership::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $memberships);
        $this->assertNotSame(
            $memberships[0]->public_id,
            $memberships[1]->public_id,
        );
    }

    public function test_membership_relationships_resolve_correct_models(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $membership = Membership::query()->firstOrFail();

        $this->assertInstanceOf(
            Organization::class,
            $membership->organization,
        );

        $this->assertSame(
            $organization->id,
            $membership->organization->id,
        );

        $this->assertInstanceOf(
            User::class,
            $membership->user,
        );

        $this->assertSame(
            $user->id,
            $membership->user->id,
        );
    }
}
