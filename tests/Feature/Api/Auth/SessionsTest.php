<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_sessions(): void
    {
        $user = User::factory()->create();

        $firstToken = $user->createToken(
            name: 'first-device',
            abilities: ['*'],
        );

        $secondToken = $user->createToken(
            name: 'second-device',
            abilities: ['*'],
        );

        $response = $this
            ->withToken($firstToken->plainTextToken)
            ->getJson('/api/v1/auth/sessions');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Active sessions retrieved successfully.',
            )
            ->assertJsonCount(2, 'data.sessions')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sessions' => [
                        '*' => [
                            'public_id',
                            'name',
                            'last_used_at',
                            'expires_at',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        $this->assertNotSame(
            $firstToken->accessToken->id,
            $secondToken->accessToken->id,
        );
    }

    public function test_sessions_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/sessions');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_user_can_revoke_a_single_session(): void
    {
        $user = User::factory()->create();

        $firstToken = $user->createToken(
            name: 'first-device',
            abilities: ['*'],
        );

        $secondToken = $user->createToken(
            name: 'second-device',
            abilities: ['*'],
        );

        $response = $this
            ->withToken($firstToken->plainTextToken)
            ->deleteJson(
                '/api/v1/auth/sessions/'.$secondToken->accessToken->public_id,
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Session revoked successfully.',
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            ['id' => $secondToken->accessToken->id],
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            ['id' => $firstToken->accessToken->id],
        );
    }

    public function test_revoke_session_returns_not_found_for_unknown_session(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'current-device',
            abilities: ['*'],
        );

        $response = $this
            ->withToken($token->plainTextToken)
            ->deleteJson(
                '/api/v1/auth/sessions/tok_01INVALIDSESSION00000000000000',
            );

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Session not found.');
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userToken = $user->createToken(
            name: 'current-device',
            abilities: ['*'],
        );

        $otherToken = $otherUser->createToken(
            name: 'other-device',
            abilities: ['*'],
        );

        $response = $this
            ->withToken($userToken->plainTextToken)
            ->deleteJson(
                '/api/v1/auth/sessions/'.$otherToken->accessToken->public_id,
            );

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Session not found.');

        $this->assertDatabaseHas(
            'personal_access_tokens',
            ['id' => $otherToken->accessToken->id],
        );
    }

    public function test_user_can_revoke_all_other_sessions(): void
    {
        $user = User::factory()->create();

        $currentToken = $user->createToken(
            name: 'current-device',
            abilities: ['*'],
        );

        $secondToken = $user->createToken(
            name: 'second-device',
            abilities: ['*'],
        );

        $thirdToken = $user->createToken(
            name: 'third-device',
            abilities: ['*'],
        );

        $response = $this
            ->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/v1/auth/sessions');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Other sessions revoked successfully.',
            );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            ['id' => $currentToken->accessToken->id],
        );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            ['id' => $secondToken->accessToken->id],
        );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            ['id' => $thirdToken->accessToken->id],
        );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_revoke_other_sessions_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/auth/sessions');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_session_public_ids_use_tok_prefix(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'test-device',
            abilities: ['*'],
        );

        $this->assertNotNull($token->accessToken->public_id);
        $this->assertStringStartsWith(
            'tok_',
            $token->accessToken->public_id,
        );
        $this->assertNotSame(
            (string) $token->accessToken->id,
            $token->accessToken->public_id,
        );
    }
}
