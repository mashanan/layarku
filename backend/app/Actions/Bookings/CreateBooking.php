<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a PENDING_PAYMENT booking that holds seats for 10 minutes.
 *
 * Double booking is prevented in two layers (SPEC 4.2):
 *
 *  1. The showtime row is locked with SELECT ... FOR UPDATE. Every booking
 *     attempt for the same showtime waits for the previous one to commit,
 *     so "check the seats are free" and "insert them" can't interleave.
 *     Bookings for *different* showtimes don't block each other.
 *  2. booking_seats has a unique index on (showtime_id, seat_id). If some
 *     future code path forgets the lock, the database still refuses the
 *     second insert, and we turn that into the same 409 response.
 */
class CreateBooking
{
    public function __construct(private readonly BookingStateMachine $stateMachine) {}

    /** @param list<int> $seatIds */
    public function __invoke(User $user, int $showtimeId, array $seatIds): Booking
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));

        try {
            return DB::transaction(fn () => $this->createLocked($user, $showtimeId, $seatIds));
        } catch (UniqueConstraintViolationException) {
            // Second line of defence (see class comment).
            throw $this->seatTaken($seatIds);
        }
    }

    private function createLocked(User $user, int $showtimeId, array $seatIds): Booking
    {
        // Layer 1: serialise all booking attempts for this showtime.
        $showtime = Showtime::whereKey($showtimeId)->lockForUpdate()->firstOrFail();

        $this->assertBookable($showtime);
        $this->assertSeatCount($seatIds);
        $this->assertSeatsBelongToStudio($showtime, $seatIds);

        // Holds that ran out but weren't cleaned up by the expiry job yet must
        // not block this customer. We already hold the showtime lock, so this
        // follows the global lock order (showtime, then booking).
        $this->expireStaleHolds($showtime);

        $taken = BookingSeat::where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $seatIds)
            ->orderBy('seat_id')
            ->pluck('seat_id')
            ->all();

        if ($taken !== []) {
            throw $this->seatTaken($taken);
        }

        $booking = Booking::create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => BookingStatus::PendingPayment,
            // Price is copied now, so later price changes don't affect this order.
            'total_idr' => $showtime->price_idr * count($seatIds),
            'expires_at' => now()->addMinutes(config('cinema.hold_minutes')),
        ]);

        $booking->seats()->createMany(array_map(fn (int $seatId) => [
            'showtime_id' => $showtime->id,
            'seat_id' => $seatId,
            'price_idr' => $showtime->price_idr,
        ], $seatIds));

        return $booking;
    }

    private function assertBookable(Showtime $showtime): void
    {
        if (! $showtime->isBookable()) {
            throw new ApiException(
                'booking_closed',
                'Booking for this showtime has closed.',
                422,
                ['booking_closes_at' => $showtime->bookingClosesAt()->toIso8601ZuluString()],
            );
        }
    }

    /** The request validates this too; the action defends its own rule. */
    private function assertSeatCount(array $seatIds): void
    {
        $max = config('cinema.max_seats_per_booking');

        if ($seatIds === [] || count($seatIds) > $max) {
            throw new ApiException('invalid_seat_count', "A booking must have between 1 and {$max} seats.", 422, ['max' => $max]);
        }
    }

    private function assertSeatsBelongToStudio(Showtime $showtime, array $seatIds): void
    {
        $valid = Seat::where('studio_id', $showtime->studio_id)->whereIn('id', $seatIds)->pluck('id')->all();
        $invalid = array_values(array_diff($seatIds, $valid));

        if ($invalid !== []) {
            sort($invalid);
            throw new ApiException('invalid_seats', 'Some seats do not belong to this showtime\'s studio.', 422, ['seat_ids' => $invalid]);
        }
    }

    private function expireStaleHolds(Showtime $showtime): void
    {
        Booking::where('showtime_id', $showtime->id)
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get()
            ->each(fn (Booking $stale) => $this->stateMachine->transition($stale, BookingStatus::Expired));
    }

    private function seatTaken(array $seatIds): ApiException
    {
        return new ApiException('seat_taken', 'Some of the selected seats are no longer available.', 409, ['seat_ids' => array_values($seatIds)]);
    }
}
