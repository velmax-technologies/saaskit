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

        return $organization instanceof Organization
            && $this->user()
                ->organizations()
                ->whereKey($organization->getKey())
                ->wherePivotIn('role', [
                    Organization::ROLE_OWNER,
                    Organization::ROLE_ADMIN,
                ])
                ->exists();
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
