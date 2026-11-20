<?php

namespace App\Http\Resources;

use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * One movie's showtimes for one date, grouped by cinema (SPEC 3: "grouped
 * per cinema and studio"). Each showtime carries its studio.
 *
 *   { "date": "2026-11-21", "timezone": "Asia/Jakarta",
 *     "cinemas": [ { "id": 1, "name": "...", "city": "...", "showtimes": [ ... ] } ] }
 */
class MovieScheduleResource extends JsonResource
{
    /** @param Collection<int, Showtime> $showtimes */
    public function __construct(private readonly CarbonImmutable $date, Collection $showtimes)
    {
        parent::__construct($showtimes);
    }

    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'timezone' => config('cinema.timezone'),
            'cinemas' => $this->resource
                ->groupBy(fn (Showtime $s) => $s->studio->cinema_id)
                ->map(function (Collection $showtimes) {
                    $cinema = $showtimes->first()->studio->cinema;

                    return [
                        'id' => $cinema->id,
                        'name' => $cinema->name,
                        'city' => $cinema->city,
                        'showtimes' => ShowtimeResource::collection($showtimes),
                    ];
                })
                ->sortBy('name')
                ->values(),
        ];
    }
}
