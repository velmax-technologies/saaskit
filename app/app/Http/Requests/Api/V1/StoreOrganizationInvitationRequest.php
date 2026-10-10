<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        if (! $organization instanceof Organization) {
            return false;
        }

        $membership = $this->user()
            ->organizations()
            ->whereKey($organization->getKey())
            ->first();

        if (! $membership ||
            ! in_array($membership->pivot->role, [
                Organization::ROLE_OWNER,
                Organization::ROLE_ADMIN,
            ], true)) {
            return false;
        }

        return ! (
            $membership->pivot->role === Organization::ROLE_ADMIN
            && $this->input('role', Organization::ROLE_MEMBER) === Organization::ROLE_ADMIN
        );
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'sometimes',
                'string',
                Rule::in([
                    Organization::ROLE_ADMIN,
                    Organization::ROLE_MEMBER,
                ]),
            ],
        ];
    }
}
