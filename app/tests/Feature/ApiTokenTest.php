<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_generates_prefixed_public_id(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('public-id-test');

        $apiToken = $token->accessToken;

        $this->assertNotNull($apiToken->public_id);
        $this->assertStringStartsWith('tok_', $apiToken->public_id);
        $this->assertNotSame((string) $apiToken->id, $apiToken->public_id);
    }

    public function test_api_token_public_ids_are_unique(): void
    {
        $user = User::factory()->create();

        $first = $user->createToken('first');
        $second = $user->createToken('second');

        $this->assertNotSame(
            $first->accessToken->public_id,
            $second->accessToken->public_id,
        );
    }
}
