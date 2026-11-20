<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only place that changes a booking's status.
 *
 * Keeping this in one class means the rules of SPEC 4.3 cannot drift apart
 * between features: cancelling (phase 2), paying (phase 5) and scanning
 * (phase 7) all go through here, and seats are released in exactly one spot.
 *
 * Callers must already hold the locks they need. The lock order everywhere
 * is: showtime row first, then booking row. Using the same order in every
 * code path is what prevents deadlocks between, say, a customer booking and
 * the expiry job.
 */
class BookingStateMachine
{
    public function transition(Booking $booking, BookingStatus $to): Booking
    {
        if (DB::transactionLevel() === 0) {
            // A status change plus seat release must be atomic.
            throw new LogicException('Booking status changes must run inside a database transaction.');
        }

        $from = $booking->status;

        if (! $from->canTransitionTo($to)) {
            throw new ApiException(
                'invalid_status_transition',
                "A {$from->value} booking cannot become {$to->value}.",
                422,
                ['from' => $from->value, 'to' => $to->value],
            );
        }

        $booking->status = $to;
        if ($to === BookingStatus::Used) {
            $booking->used_at = now();
        }
        $booking->save();

        if ($to->releasesSeats()) {
            // Deleting the rows frees the (showtime_id, seat_id) unique slots,
            // so these seats can be booked again immediately.
            $booking->seats()->delete();
        }

        return $booking;
    }
}
