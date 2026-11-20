<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'password' => ['required', 'string', Password::min(8)],
            // Shown in the token list, e.g. "Pixel 8". Lets a user revoke one device.
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
