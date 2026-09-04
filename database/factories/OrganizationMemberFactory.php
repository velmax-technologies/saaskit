<?php

namespace Database\Factories;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ];
    }

    /**
     * Create an organization owner membership.
     */
    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::OWNER->value,
        ]);
    }

    /**
     * Create an organization admin membership.
     */
    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::ADMIN->value,
        ]);
    }

    /**
     * Create an organization member membership.
     */
    public function member(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::MEMBER->value,
        ]);
    }

    /**
     * Create a membership where the user voluntarily left.
     */
    public function left(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationMemberStatus::LEFT->value,
        ]);
    }

    /**
     * Create a membership that was removed by an administrator.
     */
    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationMemberStatus::REMOVED->value,
        ]);
    }

    /**
     * Create an active membership.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationMemberStatus::ACTIVE->value,
        ]);
    }
}