<?php

namespace Database\Factories;

use App\Actions\Seats\GenerateStudioSeats;
use App\Models\Cinema;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Studio>
 */
class StudioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cinema_id' => Cinema::factory(),
            'name' => 'Studio '.fake()->unique()->numberBetween(1, 999),
        ];
    }

    /** Give the studio a small seat layout (rows x seats per row). */
    public function withSeats(int $rows = 3, int $seatsPerRow = 4, array $aisleAfter = []): static
    {
        return $this->afterCreating(
            fn (Studio $studio) => app(GenerateStudioSeats::class)($studio, $rows, $seatsPerRow, $aisleAfter)
        );
    }
}
