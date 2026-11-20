<?php

/*
| Worker for BookingConcurrencyTest. Runs in its own PHP process with its own
| database connection, exactly like a separate web request would.
|
| Usage: php book_seats.php <userId> <showtimeId> <startAt> <seatId> [<seatId> ...]
|
| It boots Laravel, opens the DB connection, then busy-waits until the shared
| <startAt> moment (unix time with microseconds) so all workers fire at once.
| Prints one JSON line with the outcome.
*/

use App\Actions\Bookings\CreateBooking;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $userId, $showtimeId, $startAt] = $argv;
$seatIds = array_map('intval', array_slice($argv, 4));

$user = User::findOrFail((int) $userId);
DB::connection()->getPdo(); // connect before the barrier, not after

$late = microtime(true) > (float) $startAt;
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $booking = app(CreateBooking::class)($user, (int) $showtimeId, $seatIds);
    echo json_encode(['ok' => true, 'booking_id' => $booking->id, 'late' => $late]);
} catch (ApiException $e) {
    echo json_encode(['ok' => false, 'code' => $e->errorCode, 'details' => $e->details, 'late' => $late]);
}
