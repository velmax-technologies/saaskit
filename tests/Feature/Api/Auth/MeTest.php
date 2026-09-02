<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'api',
            abilities: ['*'],
        )->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Authenticated user retrieved successfully.',
            )
            ->assertJsonPath('data.user.public_id', $user->public_id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissingPath('data.user.id');
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_endpoint_rejects_invalid_token(): void
    {
        $response = $this
            ->withToken('invalid-token')
            ->getJson('/api/v1/auth/me');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_endpoint_returns_only_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Authenticated User',
            'email' => 'authenticated@example.com',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
        ]);

        $token = $user->createToken(
            name: 'api',
            abilities: ['*'],
        )->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.public_id', $user->public_id)
            ->assertJsonMissing([
                'email' => $otherUser->email,
            ]);
    }
}
