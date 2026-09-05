<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_organization_is_resolved_for_an_active_member(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->for($user)
            ->for($organization)
            ->active()
            ->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}",
        );

        $response->assertSuccessful();

        $currentOrganization = app(CurrentOrganization::class);

        $this->assertFalse(
            $currentOrganization->has(),
            'Tenant context should be cleared after the request.',
        );
    }

    public function test_current_organization_context_is_cleared_after_request(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->for($user)
            ->for($organization)
            ->active()
            ->create();

        $this->actingAs($user, 'sanctum');

        $this->getJson(
            "/api/v1/organizations/{$organization->public_id}",
        );

        $currentOrganization = app(CurrentOrganization::class);

        $this->assertFalse($currentOrganization->has());
    }

    public function test_non_member_is_not_blocked_by_tenant_middleware(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->public_id}",
        );

        /*
         * ResolveOrganization only establishes tenant context.
         * Membership authorization remains the responsibility
         * of the policy/controller layer.
         */
        $response->assertForbidden();
    }

    public function test_left_member_reaches_rejoin_endpoint(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->for($user)
            ->for($organization)
            ->left()
            ->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/rejoin",
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_removed_member_reaches_rejoin_endpoint(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->for($user)
            ->for($organization)
            ->removed()
            ->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->public_id}/rejoin",
        );

        $response->assertStatus(422);
    }

    public function test_current_organization_is_not_available_outside_tenant_request(): void
    {
        $currentOrganization = app(CurrentOrganization::class);

        $this->assertFalse($currentOrganization->has());
    }

    public function test_numeric_organization_id_does_not_resolve(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        OrganizationMember::factory()
            ->for($user)
            ->for($organization)
            ->active()
            ->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}",
        );

        $response->assertNotFound();
    }
}