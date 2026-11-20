<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH semantics: only the fields that are sent are changed.
 * Email, password and role are intentionally not editable here.
 */
class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
        ];
    }
}
