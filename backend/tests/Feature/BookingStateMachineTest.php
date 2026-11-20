<?php

use App\Actions\Bookings\BookingStateMachine;
use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Showtime;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $studio = studioWithSeats(rows: 1, seatsPerRow: 2);
    $this->showtime = Showtime::factory()->for($studio)->create();
    $this->seats = $studio->seats;
    $this->machine = app(BookingStateMachine::class);
});

function bookingIn(BookingStatus $status, $test): Booking
{
    return Booking::factory()->for($test->showtime)
        ->state(['status' => $status])
        ->withSeats(...$test->seats->all())
        ->create();
}

/** Every pair from the SPEC 4.3 diagram, plus everything that must be rejected. */
dataset('allowed transitions', [
    'pay' => [BookingStatus::PendingPayment, BookingStatus::Confirmed],
    'expire' => [BookingStatus::PendingPayment, BookingStatus::Expired],
    'cancel' => [BookingStatus::PendingPayment, BookingStatus::Cancelled],
    'scan' => [BookingStatus::Confirmed, BookingStatus::Used],
]);

dataset('rejected transitions', function () {
    $allowed = [
        'PENDING_PAYMENT>CONFIRMED', 'PENDING_PAYMENT>EXPIRED', 'PENDING_PAYMENT>CANCELLED', 'CONFIRMED>USED',
    ];

    foreach (BookingStatus::cases() as $from) {
        foreach (BookingStatus::cases() as $to) {
            if (! in_array("{$from->value}>{$to->value}", $allowed, true)) {
                yield "{$from->value} to {$to->value}" => [$from, $to];
            }
        }
    }
});

it('allows the transitions in the diagram', function (BookingStatus $from, BookingStatus $to) {
    $booking = bookingIn($from, $this);

    DB::transaction(fn () => $this->machine->transition($booking, $to));

    expect($booking->fresh()->status)->toBe($to);
})->with('allowed transitions');

it('rejects every other transition with 422', function (BookingStatus $from, BookingStatus $to) {
    $booking = bookingIn($from, $this);

    try {
        DB::transaction(fn () => $this->machine->transition($booking, $to));
        $this->fail('Expected an ApiException');
    } catch (ApiException $e) {
        expect($e->errorCode)->toBe('invalid_status_transition')
            ->and($e->status)->toBe(422);
    }

    expect($booking->fresh()->status)->toBe($from);
})->with('rejected transitions');

it('releases the seats when a booking expires or is cancelled', function (BookingStatus $to) {
    $booking = bookingIn(BookingStatus::PendingPayment, $this);

    DB::transaction(fn () => $this->machine->transition($booking, $to));

    expect(BookingSeat::where('booking_id', $booking->id)->count())->toBe(0);
})->with([BookingStatus::Expired, BookingStatus::Cancelled]);

it('keeps the seats when a booking is paid', function () {
    $booking = bookingIn(BookingStatus::PendingPayment, $this);

    DB::transaction(fn () => $this->machine->transition($booking, BookingStatus::Confirmed));

    expect(BookingSeat::where('booking_id', $booking->id)->count())->toBe(2);
});

it('records when a ticket was used', function () {
    $booking = bookingIn(BookingStatus::Confirmed, $this);

    DB::transaction(fn () => $this->machine->transition($booking, BookingStatus::Used));

    expect($booking->fresh()->used_at)->not->toBeNull();
});

it('refuses to run outside a transaction', function () {
    // RefreshDatabase wraps each test in a transaction, so step out of it first.
    $booking = bookingIn(BookingStatus::PendingPayment, $this);
    $level = DB::transactionLevel();
    for ($i = 0; $i < $level; $i++) {
        DB::rollBack();
    }

    try {
        expect(fn () => $this->machine->transition($booking, BookingStatus::Cancelled))
            ->toThrow(LogicException::class);
    } finally {
        for ($i = 0; $i < $level; $i++) {
            DB::beginTransaction();
        }
    }
});
