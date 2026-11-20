<?php

namespace App\Enums;

/**
 * Booking lifecycle (SPEC 4.3):
 *
 *   PENDING_PAYMENT --paid--> CONFIRMED --scanned--> USED
 *         |--10 min--> EXPIRED
 *         `--cancel--> CANCELLED
 *
 * Changes are applied only by App\Actions\Bookings\BookingStateMachine.
 */
enum BookingStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case Confirmed = 'CONFIRMED';
    case Used = 'USED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /** @return list<self> statuses this one may move to */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Expired, self::Cancelled],
            self::Confirmed => [self::Used],
            // Final states.
            self::Used, self::Expired, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Leaving into one of these frees the seats. */
    public function releasesSeats(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }

    /** Statuses whose seats count as sold on the seat map. */
    public static function soldStatuses(): array
    {
        return [self::Confirmed, self::Used];
    }
}
