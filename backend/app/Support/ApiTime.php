<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Every timestamp in API responses: ISO 8601 in UTC, e.g. 2026-11-21T03:00:00Z. */
final class ApiTime
{
    public static function format(?DateTimeInterface $time): ?string
    {
        return $time ? Carbon::instance($time)->utc()->toIso8601ZuluString() : null;
    }
}
