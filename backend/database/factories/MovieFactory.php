<?php

namespace Database\Factories;

use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tmdb_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'title' => ucwords(fake()->words(3, true)),
            'overview' => fake()->paragraph(),
            'poster_path' => '/'.fake()->lexify('??????????').'.jpg',
            'backdrop_path' => null,
            'genres' => ['Drama'],
            'duration_minutes' => 120,
            'age_rating' => '13+',
            'release_date' => now()->subWeek()->toDateString(),
            'status' => MovieStatus::NowShowing,
            'synced_at' => now(),
        ];
    }

    public function comingSoon(): static
    {
        return $this->state(fn () => [
            'status' => MovieStatus::ComingSoon,
            'release_date' => now()->addMonth()->toDateString(),
        ]);
    }
}
