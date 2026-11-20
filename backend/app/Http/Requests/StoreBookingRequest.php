<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],
            'seat_ids' => ['required', 'array', 'min:1', 'max:'.config('cinema.max_seats_per_booking')],
            'seat_ids.*' => ['integer', 'distinct'],
        ];
    }
}
