<?php

namespace Database\Seeders;

use App\Actions\Seats\GenerateStudioSeats;
use App\Models\Cinema;
use Illuminate\Database\Seeder;

/**
 * Fictional cinema chain "Layar Kota Cinema". Names and addresses are made up
 * on purpose: the app must never look like a real cinema brand.
 */
class CinemaSeeder extends Seeder
{
    /** cinema => [studio name => [rows, seats per row, aisle after seat numbers]] */
    private const LAYOUT = [
        [
            'name' => 'Layar Kota Cinema Bandung',
            'city' => 'Bandung',
            'address' => 'Jl. Layar Kota No. 12, Bandung',
            'studios' => [
                'Studio 1' => [8, 12, [2, 10]],
                'Studio 2' => [6, 10, [5]],
            ],
        ],
        [
            'name' => 'Layar Kota Cinema Jakarta',
            'city' => 'Jakarta',
            'address' => 'Jl. Layar Kota No. 7, Jakarta Selatan',
            'studios' => [
                'Studio 1' => [10, 14, [3, 11]],
                'Studio 2' => [6, 10, [5]],
            ],
        ],
    ];

    public function run(GenerateStudioSeats $generateSeats): void
    {
        foreach (self::LAYOUT as $data) {
            $cinema = Cinema::create([
                'name' => $data['name'],
                'city' => $data['city'],
                'address' => $data['address'],
            ]);

            foreach ($data['studios'] as $studioName => [$rows, $perRow, $aisles]) {
                $studio = $cinema->studios()->create(['name' => $studioName]);
                $generateSeats($studio, $rows, $perRow, $aisles);
            }
        }
    }
}
