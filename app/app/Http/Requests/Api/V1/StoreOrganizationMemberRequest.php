<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        if (! $organization) {
            return false;
        }

        $membership = $this->user()
            ->organizations()
            ->whereKey($organization->getKey())
            ->first();

        if (! $membership ||
            ! in_array($membership->pivot->role, ['owner', 'admin'], true)) {
            return false;
        }

        return ! (
            $membership->pivot->role === 'admin'
            && $this->input('role', 'member') === 'admin'
        );
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
