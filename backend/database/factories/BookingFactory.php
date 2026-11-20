<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test/seed helper only. Real bookings are created by the CreateBooking
 * action (phase 2), which takes the row lock.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'showtime_id' => Showtime::factory(),
            'status' => BookingStatus::PendingPayment,
            'total_idr' => 0,
            'ticket_code' => null,
            'expires_at' => now()->addMinutes(config('cinema.hold_minutes')),
            'used_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'ticket_code' => Str::random(24),
            'expires_at' => null,
        ]);
    }

    /** Attach seats (and their booking_seats rows) and set the total. */
    public function withSeats(Seat ...$seats): static
    {
        return $this->afterCreating(function (Booking $booking) use ($seats) {
            $price = Showtime::find($booking->showtime_id)->price_idr;

            foreach ($seats as $seat) {
                $booking->seats()->create([
                    'showtime_id' => $booking->showtime_id,
                    'seat_id' => $seat->id,
                    'price_idr' => $price,
                ]);
            }

            $booking->update(['total_idr' => $price * count($seats)]);
        });
    }
}
