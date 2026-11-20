<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ListShowtimesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // A calendar date in the cinema's time zone, e.g. 2026-11-21.
            'date' => ['nullable', 'date_format:Y-m-d'],
            'cinema_id' => ['nullable', 'integer', 'exists:cinemas,id'],
        ];
    }

    /** Requested local date, defaulting to "today in Jakarta". */
    public function localDate(): CarbonImmutable
    {
        $timezone = config('cinema.timezone');
        $date = $this->validated('date');

        return $date
            ? CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone)
            : CarbonImmutable::now($timezone)->startOfDay();
    }

    public function cinemaId(): ?int
    {
        $id = $this->validated('cinema_id');

        return $id !== null ? (int) $id : null;
    }
}
