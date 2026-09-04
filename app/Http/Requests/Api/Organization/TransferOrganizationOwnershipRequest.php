<?php

namespace App\Http\Requests\Api\Organization;

use Illuminate\Foundation\Http\FormRequest;

class TransferOrganizationOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('transferOwnership', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'member' => [
                'required',
                'string',
            ],
        ];
    }
}
