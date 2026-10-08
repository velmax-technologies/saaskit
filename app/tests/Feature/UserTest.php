<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_generates_prefixed_public_id(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->public_id);
        $this->assertStringStartsWith('usr_', $user->public_id);
        $this->assertSame('public_id', $user->getRouteKeyName());
        $this->assertSame($user->public_id, $user->getRouteKey());
        $this->assertNotSame((string) $user->id, $user->public_id);
    }

    public function test_user_public_ids_are_unique(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertNotSame($first->public_id, $second->public_id);
    }
}
