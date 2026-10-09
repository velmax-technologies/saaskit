<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $membership = $this->route('membership');

        if (! $organization instanceof Organization ||
            ! $membership instanceof Membership ||
            $membership->organization_id !== $organization->getKey()) {
            return false;
        }

        $actorMembership = $this->user()
            ->organizations()
            ->whereKey($organization->getKey())
            ->first();

        if (! $actorMembership) {
            return false;
        }

        if ($actorMembership->pivot->role === Organization::ROLE_OWNER) {
            return $membership->role !== Organization::ROLE_OWNER;
        }

        return $actorMembership->pivot->role === Organization::ROLE_ADMIN
            && $membership->role === Organization::ROLE_MEMBER;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in([
                    Organization::ROLE_ADMIN,
                    Organization::ROLE_MEMBER,
                ]),
            ],
        ];
    }
}
