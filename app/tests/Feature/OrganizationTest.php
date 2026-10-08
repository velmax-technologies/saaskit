<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_an_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => Organization::ROLE_OWNER,
        ]);

        $this->assertTrue(
            $user->organizations->contains($organization)
        );

        $this->assertSame(
            Organization::ROLE_OWNER,
            $user->organizations->first()->pivot->role
        );
    }

    public function test_organization_can_retrieve_its_members_with_roles(): void
    {
        $organization = Organization::factory()->create();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $organization->users()->attach([
            $owner->id => ['role' => Organization::ROLE_OWNER],
            $admin->id => ['role' => Organization::ROLE_ADMIN],
            $member->id => ['role' => Organization::ROLE_MEMBER],
        ]);

        $members = $organization->users()->get();

        $this->assertCount(3, $members);

        $this->assertSame(
            Organization::ROLE_OWNER,
            $members->firstWhere('id', $owner->id)->pivot->role
        );

        $this->assertSame(
            Organization::ROLE_ADMIN,
            $members->firstWhere('id', $admin->id)->pivot->role
        );

        $this->assertSame(
            Organization::ROLE_MEMBER,
            $members->firstWhere('id', $member->id)->pivot->role
        );
    }

    public function test_user_can_belong_to_multiple_organizations(): void
    {
        $user = User::factory()->create();

        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();

        $user->organizations()->attach([
            $firstOrganization->id => ['role' => Organization::ROLE_OWNER],
            $secondOrganization->id => ['role' => Organization::ROLE_MEMBER],
        ]);

        $this->assertCount(2, $user->organizations);

        $this->assertSame(
            Organization::ROLE_OWNER,
            $user->organizations
                ->firstWhere('id', $firstOrganization->id)
                ->pivot
                ->role
        );

        $this->assertSame(
            Organization::ROLE_MEMBER,
            $user->organizations
                ->firstWhere('id', $secondOrganization->id)
                ->pivot
                ->role
        );
    }

    public function test_duplicate_organization_membership_is_rejected(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $this->expectException(QueryException::class);

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_ADMIN,
        ]);
    }
}
