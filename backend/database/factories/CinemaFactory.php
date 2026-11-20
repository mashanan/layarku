<?php

namespace Database\Factories;

use App\Models\Cinema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cinema>
 */
class CinemaFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya']);

        return [
            'name' => "Layar Kota Cinema {$city}",
            'city' => $city,
            'address' => 'Jl. Layar Kota No. '.fake()->numberBetween(1, 99).", {$city}",
        ];
    }
}
