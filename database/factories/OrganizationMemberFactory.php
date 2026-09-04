<?php

namespace Database\Factories;

use App\Enums\OrganizationMemberRole;
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

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'role' => OrganizationMemberRole::MEMBER->value,
            'status' => 'active',
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::OWNER->value,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::ADMIN->value,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::MEMBER->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inactive',
        ]);
    }
}
