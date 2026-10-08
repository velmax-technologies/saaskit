<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Support\Api\ApiAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath(
                'data.user.id',
                fn ($id) => is_string($id) && str_starts_with($id, 'usr_'),
            )
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        $createdUser = User::query()
            ->where('email', 'test@example.com')
            ->firstOrFail();

        $this->assertNotSame(
            (string) $createdUser->id,
            $response->json('data.user.id'),
        );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath(
                'data.user.id',
                $user->public_id,
            )
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotSame(
            (string) $user->id,
            $response->json('data.user.id'),
        );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_token_without_profile_read_ability_cannot_view_me(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('limited', ['some:other:ability'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid ability provided.',
                'errors' => null,
            ]);
    }

    public function test_authenticated_user_can_view_me(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api', [ApiAbility::PROFILE_READ])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.user.email', $user->email);

        $this->assertNotSame(
            (string) $user->id,
            $response->json('data.user.id'),
        );
    }

    public function test_api_token_contains_profile_read_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api', [ApiAbility::PROFILE_READ]);

        $this->assertSame(
            [ApiAbility::PROFILE_READ],
            $token->accessToken->abilities,
        );
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_list_their_api_tokens(): void
    {
        $user = User::factory()->create();

        $user->createToken('browser', [ApiAbility::PROFILE_READ]);
        $user->createToken('mobile', ['profile:read', 'billing:read']);

        $token = $user->createToken('api', [ApiAbility::PROFILE_READ]);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/tokens');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'name' => 'api',
                'abilities' => [ApiAbility::PROFILE_READ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tokens' => [
                        '*' => [
                            'id',
                            'name',
                            'abilities',
                            'last_used_at',
                            'expires_at',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        $response->assertJsonMissing([
            'token' => $token->plainTextToken,
        ]);
    }

    public function test_user_can_only_see_their_own_api_tokens(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userToken = $user->createToken('my-token', [ApiAbility::PROFILE_READ]);
        $otherUser->createToken('other-token', [ApiAbility::PROFILE_READ]);

        $response = $this->withToken($userToken->plainTextToken)
            ->getJson('/api/v1/auth/tokens');

        $response
            ->assertOk()
            ->assertJsonPath('data.tokens.0.name', 'my-token');

        $response->assertJsonMissing([
            'name' => 'other-token',
        ]);
    }

    public function test_user_can_revoke_their_api_token(): void
    {
        $user = User::factory()->create();

        $currentToken = $user->createToken('current', [ApiAbility::PROFILE_READ]);
        $revokeToken = $user->createToken('revoke-me', [ApiAbility::PROFILE_READ]);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$revokeToken->accessToken->id)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'API token revoked successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $revokeToken->accessToken->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
    }

    public function test_user_cannot_revoke_another_users_api_token(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userToken = $user->createToken('current', [ApiAbility::PROFILE_READ]);
        $otherToken = $otherUser->createToken('other', [ApiAbility::PROFILE_READ]);

        $this->withToken($userToken->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$otherToken->accessToken->id)
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }
}
