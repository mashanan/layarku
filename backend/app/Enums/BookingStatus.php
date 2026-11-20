<?php

namespace App\Enums;

/**
 * Booking lifecycle (SPEC 4.3):
 *
 *   PENDING_PAYMENT --paid--> CONFIRMED --scanned--> USED
 *         |--10 min--> EXPIRED
 *         `--cancel--> CANCELLED
 *
 * Transition rules are enforced in phase 2; this enum only names the states.
 */
enum BookingStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case Confirmed = 'CONFIRMED';
    case Used = 'USED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /** Statuses whose seats count as sold on the seat map. */
    public static function soldStatuses(): array
    {
        return [self::Confirmed, self::Used];
    }
}
