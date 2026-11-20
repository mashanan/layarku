<?php

namespace App\Actions\Demo;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Showtime;
use App\Models\User;
use App\Support\TicketCode;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Fills a few upcoming showtimes with paid bookings so the seat map shows sold
 * seats right away, and gives the demo customer one ticket.
 *
 * Bypasses the real booking flow on purpose (no payment, no row lock): this is
 * demo data written by an operator, not a customer request. Showtimes that
 * already have bookings are skipped, so running it twice adds nothing.
 */
class SeedSampleBookings
{
    private const OCCUPANCY = 0.3;

    public function __invoke(int $showtimeLimit = 8): int
    {
        $crowd = User::where('email', DemoUserSeeder::CROWD_EMAIL)->first();
        $customer = User::where('email', DemoUserSeeder::CUSTOMER_EMAIL)->first();

        if (! $crowd) {
            return 0; // demo users not seeded yet
        }

        $showtimes = Showtime::query()
            ->where('starts_at', '>', now()->addHour())
            ->whereDoesntHave('bookings')
            ->with('studio.seats')
            ->orderBy('starts_at')
            ->limit($showtimeLimit)
            ->get();

        $created = 0;

        DB::transaction(function () use ($showtimes, $crowd, $customer, &$created) {
            foreach ($showtimes as $index => $showtime) {
                // Seeded shuffle: the same showtime always gets the same seats.
                $randomizer = new Randomizer(new Mt19937($showtime->id));
                $seats = collect($randomizer->shuffleArray($showtime->studio->seats->all()));
                $soldCount = (int) floor($seats->count() * self::OCCUPANCY);

                // Respect the 6-seat rule even for demo data.
                foreach ($seats->take($soldCount)->chunk(config('cinema.max_seats_per_booking')) as $group) {
                    $this->createPaidBooking($crowd, $showtime, $group->all());
                    $created++;
                }

                if ($index === 0 && $customer) {
                    $this->createPaidBooking($customer, $showtime, $seats->slice($soldCount, 2)->all());
                    $created++;
                }
            }
        });

        return $created;
    }

    private function createPaidBooking(User $user, Showtime $showtime, array $seats): void
    {
        $booking = Booking::create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => BookingStatus::Confirmed,
            'total_idr' => $showtime->price_idr * count($seats),
            'ticket_code' => TicketCode::generate(),
        ]);

        foreach ($seats as $seat) {
            $booking->seats()->create([
                'showtime_id' => $showtime->id,
                'seat_id' => $seat->id,
                'price_idr' => $showtime->price_idr,
            ]);
        }
    }
}
