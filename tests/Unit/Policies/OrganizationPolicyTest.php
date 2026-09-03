<?php

namespace Tests\Unit\Policies;

use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Policies\OrganizationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new OrganizationPolicy;
    }

    public function test_member_can_view_organization(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
        );

        $this->assertTrue(
            $this->policy->view($user, $organization),
        );
    }

    public function test_admin_can_update_organization(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
        );

        $this->assertTrue(
            $this->policy->update($user, $organization),
        );
    }

    public function test_member_cannot_update_organization(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
        );

        $this->assertFalse(
            $this->policy->update($user, $organization),
        );
    }

    public function test_only_owner_can_delete_organization(): void
    {
        [$owner, $organization] = $this->createMembership(
            OrganizationMemberRole::OWNER,
        );

        [$admin] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
            $organization,
        );

        [$member] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
            $organization,
        );

        $this->assertTrue(
            $this->policy->delete($owner, $organization),
        );

        $this->assertFalse(
            $this->policy->delete($admin, $organization),
        );

        $this->assertFalse(
            $this->policy->delete($member, $organization),
        );
    }

    public function test_admin_can_invite_members(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
        );

        $this->assertTrue(
            $this->policy->inviteMembers($user, $organization),
        );
    }

    public function test_member_cannot_invite_members(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
        );

        $this->assertFalse(
            $this->policy->inviteMembers($user, $organization),
        );
    }

    public function test_only_owner_can_update_member_roles(): void
    {
        [$owner, $organization] = $this->createMembership(
            OrganizationMemberRole::OWNER,
        );

        [$admin] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
            $organization,
        );

        [$member] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
            $organization,
        );

        $this->assertTrue(
            $this->policy->updateMemberRoles($owner, $organization),
        );

        $this->assertFalse(
            $this->policy->updateMemberRoles($admin, $organization),
        );

        $this->assertFalse(
            $this->policy->updateMemberRoles($member, $organization),
        );
    }

    public function test_admin_can_remove_members(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
        );

        $this->assertTrue(
            $this->policy->removeMembers($user, $organization),
        );
    }

    public function test_member_cannot_remove_members(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
        );

        $this->assertFalse(
            $this->policy->removeMembers($user, $organization),
        );
    }

    public function test_only_owner_can_transfer_ownership(): void
    {
        [$owner, $organization] = $this->createMembership(
            OrganizationMemberRole::OWNER,
        );

        [$admin] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
            $organization,
        );

        [$member] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
            $organization,
        );

        $this->assertTrue(
            $this->policy->transferOwnership($owner, $organization),
        );

        $this->assertFalse(
            $this->policy->transferOwnership($admin, $organization),
        );

        $this->assertFalse(
            $this->policy->transferOwnership($member, $organization),
        );
    }

    public function test_admin_and_member_can_leave(): void
    {
        [$admin, $organization] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
        );

        [$member] = $this->createMembership(
            OrganizationMemberRole::MEMBER,
            $organization,
        );

        $this->assertTrue(
            $this->policy->leave($admin, $organization),
        );

        $this->assertTrue(
            $this->policy->leave($member, $organization),
        );
    }

    public function test_owner_cannot_leave(): void
    {
        [$owner, $organization] = $this->createMembership(
            OrganizationMemberRole::OWNER,
        );

        $this->assertFalse(
            $this->policy->leave($owner, $organization),
        );
    }

    public function test_inactive_membership_has_no_permissions(): void
    {
        [$user, $organization] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
            status: 'inactive',
        );

        $this->assertFalse(
            $this->policy->view($user, $organization),
        );

        $this->assertFalse(
            $this->policy->update($user, $organization),
        );

        $this->assertFalse(
            $this->policy->inviteMembers($user, $organization),
        );
    }

    public function test_member_of_another_organization_has_no_access(): void
    {
        [$user, $organizationA] = $this->createMembership(
            OrganizationMemberRole::ADMIN,
        );

        $organizationB = Organization::create([
            'name' => 'Organization B',
            'slug' => 'organization-b',
        ]);

        $this->assertFalse(
            $this->policy->view($user, $organizationB),
        );

        $this->assertFalse(
            $this->policy->update($user, $organizationB),
        );

        $this->assertTrue(
            $this->policy->view($user, $organizationA),
        );
    }

    private function createMembership(
        OrganizationMemberRole $role,
        ?Organization $organization = null,
        string $status = 'active',
    ): array {
        $user = User::factory()->create();

        $organization ??= Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization-'.uniqid(),
        ]);

        OrganizationMember::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => $role->value,
            'status' => $status,
        ]);

        return [$user, $organization];
    }
}
