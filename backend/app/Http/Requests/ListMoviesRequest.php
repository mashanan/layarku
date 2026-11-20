<?php

namespace App\Http\Requests;

use App\Enums\MovieStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMoviesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // `ended` is internal; clients only browse what is on or coming.
            'status' => ['nullable', Rule::in([MovieStatus::NowShowing->value, MovieStatus::ComingSoon->value])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function status(): MovieStatus
    {
        return MovieStatus::from($this->validated('status') ?? MovieStatus::NowShowing->value);
    }
}
