<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBookingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
