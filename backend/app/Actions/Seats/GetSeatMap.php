<?php

namespace App\Actions\Seats;

use App\Enums\BookingStatus;
use App\Enums\SeatStatus;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Support\Collection;

/**
 * Status of every seat in the showtime's studio.
 *
 * A booking_seats row exists for each seat of a PENDING_PAYMENT, CONFIRMED or
 * USED booking (rows are deleted when a booking expires or is cancelled).
 * Between the hold running out and the expiry job deleting the rows, a
 * pending booking is "stale": we already report its seats as available,
 * because the customer can no longer pay for them. Phase 2's CreateBooking
 * expires stale holds inside its lock before inserting.
 */
class GetSeatMap
{
    /**
     * @return Collection<int, array{seat: Seat, status: SeatStatus}>
     */
    public function __invoke(Showtime $showtime): Collection
    {
        $soldSeatIds = $this->seatIdsWhere($showtime, fn ($q) => $q
            ->whereIn('status', BookingStatus::soldStatuses()));

        $heldSeatIds = $this->seatIdsWhere($showtime, fn ($q) => $q
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '>', now()));

        return $showtime->studio->seats()
            ->orderBy('grid_y')
            ->orderBy('grid_x')
            ->get()
            ->map(fn (Seat $seat) => [
                'seat' => $seat,
                'status' => match (true) {
                    $soldSeatIds->has($seat->id) => SeatStatus::Sold,
                    $heldSeatIds->has($seat->id) => SeatStatus::Held,
                    default => SeatStatus::Available,
                },
            ]);
    }

    /** Seat ids (as keys, for O(1) lookups) whose booking matches $bookingScope. */
    private function seatIdsWhere(Showtime $showtime, callable $bookingScope): Collection
    {
        return BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->whereHas('booking', $bookingScope)
            ->pluck('seat_id')
            ->flip();
    }
}
