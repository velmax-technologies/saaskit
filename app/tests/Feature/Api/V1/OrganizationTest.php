<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_require_authentication(): void
    {
        $this->getJson('/api/v1/organizations')
            ->assertUnauthorized();

        $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
        ])->assertUnauthorized();
    }

    public function test_created_organization_exposes_public_id_and_not_database_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Public ID Test Organization',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.organization.id', fn ($id) => is_string($id) && str_starts_with($id, 'org_'))
            ->assertJsonMissingPath('data.organization.database_id');

        $organization = Organization::query()
            ->where('name', 'Public ID Test Organization')
            ->firstOrFail();

        $response->assertJsonPath(
            'data.organization.id',
            $organization->public_id
        );

        $this->assertNotSame(
            (string) $organization->id,
            $response->json('data.organization.id')
        );
    }

    public function test_organization_list_exposes_public_id_and_not_database_id(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $user->organizations()->attach($organization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations');

        $response->assertOk()
            ->assertJsonPath(
                'data.organizations.0.id',
                $organization->public_id
            )
            ->assertJsonMissingPath('data.organizations.0.database_id');

        $this->assertNotSame(
            (string) $organization->id,
            $response->json('data.organizations.0.id')
        );
    }

    public function test_authenticated_user_can_create_an_organization(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization.name', 'Acme Inc.')
            ->assertJsonPath('data.organization.slug', 'acme-inc')
            ->assertJsonPath('data.role', Organization::ROLE_OWNER);

        $organization = Organization::where('slug', 'acme-inc')->firstOrFail();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => Organization::ROLE_OWNER,
        ]);
    }

    public function test_authenticated_user_can_create_an_organization_with_custom_slug(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc.',
            'slug' => 'acme-network',
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization.slug', 'acme-network');
    }

    public function test_organization_creation_validates_required_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_organization_slug_must_be_unique(): void
    {
        Organization::factory()->create([
            'slug' => 'acme-network',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [
            'name' => 'Another Acme',
            'slug' => 'acme-network',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_user_can_only_list_organizations_they_belong_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $organization = Organization::factory()->create([
            'name' => 'My Organization',
            'slug' => 'my-organization',
        ]);

        $otherOrganization = Organization::factory()->create([
            'name' => 'Other Organization',
            'slug' => 'other-organization',
        ]);

        $user->organizations()->attach($organization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        $otherUser->organizations()->attach($otherOrganization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'name' => 'My Organization',
                'slug' => 'my-organization',
            ])
            ->assertJsonMissing([
                'name' => 'Other Organization',
                'slug' => 'other-organization',
            ]);
    }
}
