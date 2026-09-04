<?php

namespace Tests\Feature\Api\Tenancy;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_an_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'Acme Inc',
                'slug' => 'acme-inc',
                'description' => 'Acme organization.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.organization.id',
                fn (string $id): bool => str_starts_with($id, 'org_'),
            )
            ->assertJsonPath(
                'data.organization.name',
                'Acme Inc',
            )
            ->assertJsonPath(
                'data.organization.slug',
                'acme-inc',
            )
            ->assertJsonPath(
                'data.organization.description',
                'Acme organization.',
            );

        $this->assertDatabaseHas('organizations', [
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
        ]);

        $organization = Organization::where(
            'slug',
            'acme-inc',
        )->firstOrFail();

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrganizationMemberRole::OWNER->value,
            'status' => 'active',
        ]);
    }

    public function test_organization_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
        ]);

        $response->assertUnauthorized();
    }

    public function test_organization_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/organizations', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
            ]);
    }

    public function test_organization_slug_must_be_unique(): void
    {
        $user = User::factory()->create();

        Organization::create([
            'name' => 'Existing Organization',
            'slug' => 'existing-org',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'Another Organization',
                'slug' => 'existing-org',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_organization_response_does_not_expose_numeric_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'Acme Inc',
                'slug' => 'acme-inc',
            ]);

        $response
            ->assertCreated()
            ->assertJsonMissing([
                'id' => 1,
            ])
            ->assertJsonPath(
                'data.organization.id',
                fn (string $id): bool => str_starts_with($id, 'org_'),
            );
    }
}
