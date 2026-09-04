<?php

namespace App\Http\Requests\Api\Organization;

use App\Enums\OrganizationMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('updateMemberRoles', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::in([
                    OrganizationMemberRole::ADMIN->value,
                    OrganizationMemberRole::MEMBER->value,
                ]),
            ],
        ];
    }
}
