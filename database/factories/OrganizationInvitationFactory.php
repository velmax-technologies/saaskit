<?php

namespace Database\Factories;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationInvitation>
 */
class OrganizationInvitationFactory extends Factory
{
    protected $model = OrganizationInvitation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrganizationMemberRole::MEMBER->value,
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => OrganizationInvitationStatus::PENDING->value,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationInvitationStatus::PENDING->value,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationInvitationStatus::ACCEPTED->value,
            'accepted_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationInvitationStatus::EXPIRED->value,
            'expires_at' => now()->subDay(),
            'accepted_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationInvitationStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => OrganizationMemberRole::ADMIN->value,
        ]);
    }
}
