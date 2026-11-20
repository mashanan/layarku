<?php

namespace App\Enums;

enum MovieStatus: string
{
    case NowShowing = 'now_showing';
    case ComingSoon = 'coming_soon';
    // No longer returned by TMDB's lists; kept so old bookings still resolve.
    case Ended = 'ended';
}
