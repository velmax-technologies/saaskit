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

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_password_confirmation_mismatch(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'confirmation@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'confirmation@example.com',
        ]);
    }

    public function test_login_rejects_missing_and_malformed_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_and_token_listing_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->getJson('/api/v1/auth/tokens')->assertUnauthorized();
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

    public function test_login_is_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'login-limit@example.com',
            'password' => 'password123',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many login attempts. Please try again later.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_successful_login_clears_failed_login_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'login-reset-limit@example.com',
            'password' => 'password123',
        ]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
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

        $apiToken = $token->accessToken;
        $tokenIds = collect($response->json('data.tokens'))
            ->pluck('id')
            ->all();

        $this->assertContains($apiToken->public_id, $tokenIds);

        $this->assertNotContains(
            (string) $apiToken->id,
            $tokenIds,
        );

        foreach ($tokenIds as $tokenId) {
            $this->assertIsString($tokenId);
            $this->assertStringStartsWith('tok_', $tokenId);
        }

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
            ->deleteJson('/api/v1/auth/tokens/'.$revokeToken->accessToken->public_id)
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
            ->deleteJson('/api/v1/auth/tokens/'.$otherToken->accessToken->public_id)
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }
}
