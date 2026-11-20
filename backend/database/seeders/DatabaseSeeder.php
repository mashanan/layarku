<?php

namespace Database\Seeders;

use App\Actions\Demo\SeedSampleBookings;
use App\Models\Showtime;
use Illuminate\Database\Seeder;

/**
 * `php artisan migrate:fresh --seed` creates cinemas, studios, seats and demo
 * accounts. Movies and showtimes come from TMDB via `php artisan movies:import`,
 * which also adds sample bookings. If showtimes already exist (e.g. re-seeding
 * without a fresh migration), sample bookings are added here too.
 *
 * Seeders never use model factories, because Faker is a dev-only dependency
 * and these seeders must also run on the demo server.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CinemaSeeder::class,
            DemoUserSeeder::class,
        ]);

        if (Showtime::query()->exists()) {
            app(SeedSampleBookings::class)();
        }
    }
}
