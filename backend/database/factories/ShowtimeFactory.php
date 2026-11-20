<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Showtime>
 */
class ShowtimeFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'movie_id' => Movie::factory(),
            'studio_id' => Studio::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(120),
            'price_idr' => 40000,
        ];
    }

    /** Start at a given UTC moment; ends 2 hours later. */
    public function startingAt(\DateTimeInterface|string $startsAt): static
    {
        $start = now()->parse($startsAt)->utc();

        return $this->state(fn () => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(120),
        ]);
    }
}
