<?php

namespace Tests\Feature\Api\V1;

use App\Http\Resources\Api\V1\MembershipResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MembershipResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_resource_exposes_public_membership_id(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_MEMBER,
        ]);

        $membership = $organization->users()->first()->pivot;

        $resource = new MembershipResource($membership);
        $data = $resource->toArray(Request::create('/'));

        $this->assertStringStartsWith('mem_', $data['id']);
        $this->assertNotSame((string) $membership->id, $data['id']);
        $this->assertSame(
            Organization::ROLE_MEMBER,
            $data['role'],
        );
    }

    public function test_membership_resource_uses_public_ids_for_loaded_relations(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_ADMIN,
        ]);

        $membership = $organization->users()
            ->first()
            ->pivot;

        $membership->load('organization', 'user');

        $resource = new MembershipResource($membership);
        $data = $resource->toArray(Request::create('/'));

        $this->assertSame(
            $organization->public_id,
            $data['organization']['id'],
        );

        $this->assertSame(
            $user->public_id,
            $data['user']['id'],
        );

        $this->assertNotSame(
            (string) $organization->id,
            $data['organization']['id'],
        );

        $this->assertNotSame(
            (string) $user->id,
            $data['user']['id'],
        );
    }

    public function test_membership_resource_does_not_expose_database_ids(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->users()->attach($user, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $membership = $organization->users()
            ->first()
            ->pivot;

        $membership->load('organization', 'user');

        $resource = new MembershipResource($membership);
        $data = $resource->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('database_id', $data);
        $this->assertArrayNotHasKey('organization_id', $data);
        $this->assertArrayNotHasKey('user_id', $data);

        $this->assertArrayNotHasKey('database_id', $data['organization']);
        $this->assertArrayNotHasKey('database_id', $data['user']);
    }
}
