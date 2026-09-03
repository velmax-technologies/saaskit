<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],
            'token' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }
}
