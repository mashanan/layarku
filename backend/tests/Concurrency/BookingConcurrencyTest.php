<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Facades\Process;

/*
| Real concurrency, not a simulation.
|
| Normal feature tests run inside one process and one rolled-back
| transaction, so they can't show that two simultaneous requests are
| serialised by the row lock. Here, each booking attempt is a separate PHP
| process (tests/Concurrency/book_seats.php) with its own MySQL connection,
| and all of them start at the same instant. The data they read must
| therefore be committed, which is why this folder uses DatabaseTruncation
| instead of RefreshDatabase (see tests/Pest.php).
*/

const WORKER_BOOT_SECONDS = 4;

beforeEach(function () {
    $this->studio = studioWithSeats(rows: 2, seatsPerRow: 6);
    $this->showtime = Showtime::factory()->for($this->studio)->startingAt(now()->addDay())->create(['price_idr' => 40000]);
    $this->seatIds = fn (string ...$labels) => collect($labels)
        ->map(fn ($label) => $this->studio->seats->first(fn (Seat $s) => $s->label() === $label)->id)
        ->all();
});

/**
 * Start one worker per attempt, all aimed at the same start moment.
 *
 * @param  list<list<int>>  $attempts  seat ids per worker
 * @return list<array<string, mixed>> decoded outcome per worker, same order
 */
function raceBookings(Showtime $showtime, array $attempts): array
{
    $startAt = sprintf('%.6F', microtime(true) + WORKER_BOOT_SECONDS);
    $db = config('database.connections.mysql');

    // Point the workers at the same test database, whatever .env says.
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => $db['host'],
        'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => $db['database'],
        'DB_USERNAME' => $db['username'],
        'DB_PASSWORD' => (string) $db['password'],
        'CACHE_STORE' => 'array',
    ];

    $running = [];
    foreach ($attempts as $seatIds) {
        $user = User::factory()->create();
        $running[] = Process::path(base_path())
            ->env($env)
            ->timeout(60)
            ->start([PHP_BINARY, 'tests/Concurrency/book_seats.php', $user->id, $showtime->id, $startAt, ...$seatIds]);
    }

    return array_map(function ($process) {
        $result = $process->wait();
        expect($result->successful())->toBeTrue("Worker crashed: {$result->errorOutput()}");

        $outcome = json_decode(trim($result->output()), true);
        expect($outcome)->toBeArray('Worker did not report a clean outcome. Output: '.mb_substr($result->output().$result->errorOutput(), 0, 600));

        return $outcome;
    }, $running);
}

function assertAllWorkersRacedTogether(array $outcomes): void
{
    // If a worker booted after the start moment, the race didn't really happen.
    expect(collect($outcomes)->where('late', true))->toBeEmpty('A worker started late; increase WORKER_BOOT_SECONDS.');
}

it('lets exactly one of many simultaneous requests book the same seat', function () {
    $seat = ($this->seatIds)('A1');

    $outcomes = raceBookings($this->showtime, array_fill(0, 6, $seat));

    assertAllWorkersRacedTogether($outcomes);
    $winners = collect($outcomes)->where('ok', true);
    $losers = collect($outcomes)->where('ok', false);

    expect($winners)->toHaveCount(1)
        ->and($losers)->toHaveCount(5)
        ->and($losers->pluck('code')->unique()->all())->toBe(['seat_taken'])
        ->and(BookingSeat::where('showtime_id', $this->showtime->id)->count())->toBe(1)
        ->and(Booking::count())->toBe(1);
});

it('rejects the whole booking when seat sets partially overlap (A1-A3 vs A3-A5)', function () {
    $outcomes = raceBookings($this->showtime, [
        ($this->seatIds)('A1', 'A2', 'A3'),
        ($this->seatIds)('A3', 'A4', 'A5'),
    ]);

    assertAllWorkersRacedTogether($outcomes);
    expect(collect($outcomes)->where('ok', true))->toHaveCount(1);

    $loser = collect($outcomes)->firstWhere('ok', false);
    expect($loser['code'])->toBe('seat_taken')
        ->and($loser['details']['seat_ids'])->toBe(($this->seatIds)('A3'));

    // Only the winner's three seats exist: the loser's A4/A5 were not half-booked.
    expect(BookingSeat::where('showtime_id', $this->showtime->id)->count())->toBe(3)
        ->and(Booking::where('status', BookingStatus::PendingPayment)->count())->toBe(1);
});

it('lets simultaneous bookings of different seats all succeed', function () {
    $outcomes = raceBookings($this->showtime, [
        ($this->seatIds)('A1', 'A2'),
        ($this->seatIds)('A3', 'A4'),
        ($this->seatIds)('B1', 'B2', 'B3'),
    ]);

    assertAllWorkersRacedTogether($outcomes);
    expect(collect($outcomes)->every(fn ($o) => $o['ok']))->toBeTrue()
        ->and(BookingSeat::where('showtime_id', $this->showtime->id)->count())->toBe(7);
});
