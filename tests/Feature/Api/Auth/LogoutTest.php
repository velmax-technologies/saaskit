<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'api',
            abilities: ['*'],
        )->plainTextToken;

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logged_out_token_can_no_longer_access_me(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken(
            name: 'api',
            abilities: ['*'],
        )->plainTextToken;

        $this
            ->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Force Laravel to resolve Sanctum authentication again.
        $this->app['auth']->forgetGuards();

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_only_revokes_current_token(): void
    {
        $user = User::factory()->create();

        $firstToken = $user->createToken(
            name: 'first-device',
            abilities: ['*'],
        )->plainTextToken;

        $secondToken = $user->createToken(
            name: 'second-device',
            abilities: ['*'],
        )->plainTextToken;

        $this
            ->withToken($firstToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Force a fresh Sanctum authentication lookup.
        $this->app['auth']->forgetGuards();

        $this
            ->withToken($firstToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this
            ->withToken($secondToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
