<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOrganizationOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        if (! $organization instanceof Organization) {
            return false;
        }

        return $this->user()
            ->organizations()
            ->whereKey($organization->getKey())
            ->wherePivot('role', Organization::ROLE_OWNER)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'membership_id' => [
                'required',
                'string',
                Rule::exists('organization_user', 'public_id')
                    ->where('organization_id', $organization->getKey()),
            ],
        ];
    }
}
