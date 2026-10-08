<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->organizations()
            ->whereKey($this->route('organization')->getKey())
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'string',
                'exists:users,public_id',
            ],
            'role' => [
                'sometimes',
                'string',
                Rule::in(['admin', 'member']),
            ],
        ];
    }
}
