<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\Showtime;
use Illuminate\Support\Facades\DB;

/**
 * Customer cancels an unpaid booking. Paid tickets are final: no cancellation
 * and no refund (SPEC 4.4), which is a product decision, not a limitation.
 */
class CancelBooking
{
    public function __construct(private readonly BookingStateMachine $stateMachine) {}

    public function __invoke(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            // Lock order: showtime, then booking.
            Showtime::whereKey($booking->showtime_id)->lockForUpdate()->first();
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== BookingStatus::PendingPayment) {
                throw new ApiException(
                    'booking_not_cancellable',
                    'Only unpaid bookings can be cancelled. Paid tickets are final.',
                    422,
                    ['status' => $booking->status->value],
                );
            }

            // The hold already ran out and only the cleanup job hadn't run yet.
            // The seats are released either way; report what really happened.
            $to = $booking->expires_at->isPast() ? BookingStatus::Expired : BookingStatus::Cancelled;

            return $this->stateMachine->transition($booking, $to);
        });
    }
}
