<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasPublicIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_generates_a_public_id_automatically(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->public_id);
        $this->assertStringStartsWith('usr_', $user->public_id);
        $this->assertNotSame((string) $user->id, $user->public_id);
    }

    public function test_public_id_is_used_as_the_route_key(): void
    {
        $user = User::factory()->create();

        $this->assertSame('public_id', $user->getRouteKeyName());
        $this->assertSame($user->public_id, $user->getRouteKey());
    }

    public function test_numeric_id_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create();

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('id', $serialized);
        $this->assertArrayHasKey('public_id', $serialized);
        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
    }

    public function test_public_ids_are_unique(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertNotSame($first->public_id, $second->public_id);
    }
}
