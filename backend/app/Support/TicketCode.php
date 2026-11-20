<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Ticket codes are what the QR encodes. They must be unguessable, so they are
 * long cryptographically random strings, never IDs or sequences (SPEC 4.5).
 */
final class TicketCode
{
    public const LENGTH = 24;

    public static function generate(): string
    {
        // Str::random uses random_bytes(), a CSPRNG. Upper-case only to make the
        // code easy to read aloud if a QR ever fails to scan.
        return Str::upper(Str::random(self::LENGTH));
    }
}
