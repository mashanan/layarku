<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Showtime;
use Illuminate\Support\Facades\DB;

/**
 * Moves PENDING_PAYMENT bookings whose hold ran out to EXPIRED, which frees
 * their seats. Run every minute by the scheduler (routes/console.php).
 *
 * Each booking gets its own short transaction that locks the showtime and
 * then the booking (the global lock order), and re-checks the status after
 * locking. In phase 5 a payment webhook may confirm the same booking at the
 * same moment; whichever commits first wins, and the other sees the new
 * status and does nothing.
 */
class ExpireUnpaidBookings
{
    public function __construct(private readonly BookingStateMachine $stateMachine) {}

    public function __invoke(): int
    {
        $expired = 0;

        Booking::query()
            ->select(['id', 'showtime_id'])
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($candidates) use (&$expired) {
                foreach ($candidates as $candidate) {
                    $expired += $this->expireOne($candidate->id, $candidate->showtime_id) ? 1 : 0;
                }
            });

        return $expired;
    }

    private function expireOne(int $bookingId, int $showtimeId): bool
    {
        return DB::transaction(function () use ($bookingId, $showtimeId) {
            Showtime::whereKey($showtimeId)->lockForUpdate()->first();
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->first();

            // Someone else (payment, cancel, CreateBooking) may have got here first.
            if (! $booking
                || $booking->status !== BookingStatus::PendingPayment
                || $booking->expires_at->isFuture()) {
                return false;
            }

            $this->stateMachine->transition($booking, BookingStatus::Expired);

            return true;
        });
    }
}
