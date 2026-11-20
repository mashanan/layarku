<?php

namespace App\Enums;

/** How a seat looks on the seat map for one showtime. */
enum SeatStatus: string
{
    case Available = 'available';
    // In an unpaid booking whose 10-minute hold has not run out yet.
    case Held = 'held';
    // In a paid (CONFIRMED) or already scanned (USED) booking.
    case Sold = 'sold';
}
