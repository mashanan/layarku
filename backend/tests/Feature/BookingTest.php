<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-19 03:00:00', 'UTC'));
    $this->user = actingAsCustomer();
    // 2 rows x 5 seats: A1..A5, B1..B5
    $this->studio = studioWithSeats(rows: 2, seatsPerRow: 5);
    $this->showtime = Showtime::factory()->for($this->studio)
        ->startingAt('2026-11-19 10:00:00')->create(['price_idr' => 45000]);
    $this->seatIds = fn (string ...$labels) => collect($labels)
        ->map(fn ($label) => $this->studio->seats->first(fn (Seat $s) => $s->label() === $label)->id)
        ->all();
});

function book($test, array $seatIds, ?int $showtimeId = null)
{
    return $test->postJson('/api/bookings', [
        'showtime_id' => $showtimeId ?? $test->showtime->id,
        'seat_ids' => $seatIds,
    ]);
}

describe('creating a booking', function () {
    it('holds the seats for 10 minutes and copies the price', function () {
        $response = book($this, ($this->seatIds)('A2', 'A1'));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'PENDING_PAYMENT')
            ->assertJsonPath('data.total_idr', 90000)
            ->assertJsonPath('data.expires_at', '2026-11-19T03:10:00Z')
            ->assertJsonPath('data.ticket_code', null)
            ->assertJsonPath('data.seats.0.label', 'A1')
            ->assertJsonPath('data.seats.1.label', 'A2')
            ->assertJsonPath('data.seats.0.price_idr', 45000)
            ->assertJsonPath('data.studio.id', $this->studio->id);

        $booking = Booking::find($response->json('data.id'));
        expect($booking->user_id)->toBe($this->user->id);

        // A later price change does not touch the existing booking.
        $this->showtime->update(['price_idr' => 99000]);
        expect($booking->fresh()->total_idr)->toBe(90000);
    });

    it('shows the new seats as held on the seat map', function () {
        book($this, ($this->seatIds)('B3'))->assertCreated();

        $statuses = collect($this->getJson("/api/showtimes/{$this->showtime->id}/seats")->json('data.seats'))
            ->pluck('status', 'label');
        expect($statuses['B3'])->toBe('held')->and($statuses['B4'])->toBe('available');
    });

    it('rejects seats that are already taken and lists them', function () {
        Booking::factory()->confirmed()->for($this->showtime)
            ->withSeats(...$this->studio->seats->whereIn('id', ($this->seatIds)('A3', 'A4'))->all())
            ->create();

        $response = book($this, ($this->seatIds)('A2', 'A3', 'A4'));

        expectApiError($response, 409, 'seat_taken');
        expect($response->json('error.details.seat_ids'))->toBe(($this->seatIds)('A3', 'A4'));
        // All or nothing: A2 was not booked either.
        expect(Booking::count())->toBe(1);
    });

    it('allows booking seats whose hold has run out, even before the expiry job runs', function () {
        $stale = Booking::factory()->for($this->showtime)
            ->state(['expires_at' => now()->subMinute()])
            ->withSeats(...$this->studio->seats->whereIn('id', ($this->seatIds)('A1'))->all())
            ->create();

        book($this, ($this->seatIds)('A1'))->assertCreated();

        expect($stale->fresh()->status)->toBe(BookingStatus::Expired);
    });

    it('allows the same seat on a different showtime', function () {
        $other = Showtime::factory()->for($this->studio)->startingAt('2026-11-19 14:00:00')->create();
        Booking::factory()->confirmed()->for($other)
            ->withSeats(...$this->studio->seats->whereIn('id', ($this->seatIds)('A1'))->all())
            ->create();

        book($this, ($this->seatIds)('A1'))->assertCreated();
    });

    it('allows at most 6 seats', function () {
        $seven = ($this->seatIds)('A1', 'A2', 'A3', 'A4', 'A5', 'B1', 'B2');

        expectApiError(book($this, $seven), 422, 'validation_failed');
        book($this, array_slice($seven, 0, 6))->assertCreated();
    });

    it('rejects an empty or duplicated seat list', function () {
        expectApiError(book($this, []), 422, 'validation_failed');
        expectApiError(book($this, ($this->seatIds)('A1', 'A1')), 422, 'validation_failed');
    });

    it('rejects seats from another studio', function () {
        $foreign = studioWithSeats(rows: 1, seatsPerRow: 1)->seats->first();

        $response = book($this, [...($this->seatIds)('A1'), $foreign->id]);

        expectApiError($response, 422, 'invalid_seats');
        expect($response->json('error.details.seat_ids'))->toBe([$foreign->id]);
    });

    it('closes booking 15 minutes before the start', function () {
        // now = 03:00 UTC
        $closed = Showtime::factory()->for($this->studio)->startingAt('2026-11-19 03:15:00')->create();
        $open = Showtime::factory()->for($this->studio)->startingAt('2026-11-19 03:16:00')->create();

        expectApiError(book($this, ($this->seatIds)('A1'), $closed->id), 422, 'booking_closed');
        book($this, ($this->seatIds)('A1'), $open->id)->assertCreated();
    });

    it('rejects an unknown showtime', function () {
        expectApiError(book($this, ($this->seatIds)('A1'), 999999), 422, 'validation_failed');
    });

    it('is rate limited', function () {
        for ($i = 0; $i < 10; $i++) {
            book($this, []); // cheap invalid requests still count
        }

        expectApiError(book($this, ($this->seatIds)('A1')), 429, 'too_many_requests');
    });

    it('has a unique index as the second line of defence', function () {
        // Simulate a code path that skipped the lock: insert directly, twice.
        // (CreateBooking catches this exception and answers 409 seat_taken.)
        $booking = Booking::factory()->for($this->showtime)->create();
        $row = ['booking_id' => $booking->id, 'showtime_id' => $this->showtime->id, 'seat_id' => ($this->seatIds)('A1')[0], 'price_idr' => 1];
        BookingSeat::create($row);

        expect(fn () => BookingSeat::create($row))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('reading bookings', function () {
    it('lists only my bookings, newest first', function () {
        $older = Booking::factory()->for($this->user)->for($this->showtime)->create(['created_at' => now()->subHour()]);
        $newer = Booking::factory()->for($this->user)->for($this->showtime)->create();
        Booking::factory()->for($this->showtime)->create(); // someone else's

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    });

    it('shows the ticket code only for paid tickets', function () {
        $paid = Booking::factory()->confirmed()->for($this->user)->for($this->showtime)->create();
        $pending = Booking::factory()->for($this->user)->for($this->showtime)
            ->create(['ticket_code' => 'SHOULDNOTSHOW1234567890X']);

        $this->getJson("/api/bookings/{$paid->id}")
            ->assertOk()
            ->assertJsonPath('data.ticket_code', $paid->ticket_code)
            ->assertJsonPath('data.expires_at', null);
        $this->getJson("/api/bookings/{$pending->id}")->assertJsonPath('data.ticket_code', null);
    });

    it('flags paid tickets for shows that have ended', function () {
        $booking = Booking::factory()->confirmed()->for($this->user)->for($this->showtime)->create();

        $this->getJson("/api/bookings/{$booking->id}")->assertJsonPath('data.is_past', false);

        $this->travelTo(now()->parse('2026-11-19 12:30:00', 'UTC')); // show ended at 12:00
        $this->getJson("/api/bookings/{$booking->id}")
            ->assertJsonPath('data.is_past', true)
            ->assertJsonPath('data.status', 'CONFIRMED');
    });

    it("hides other customers' bookings behind a 404", function () {
        $theirs = Booking::factory()->for(User::factory())->for($this->showtime)->create();

        expectApiError($this->getJson("/api/bookings/{$theirs->id}"), 404, 'not_found');
        expectApiError($this->postJson("/api/bookings/{$theirs->id}/cancel"), 404, 'not_found');
        expect($theirs->fresh()->status)->toBe(BookingStatus::PendingPayment);
    });
});

describe('cancelling', function () {
    it('cancels an unpaid booking and frees its seats', function () {
        $id = book($this, ($this->seatIds)('A1', 'A2'))->json('data.id');

        $this->postJson("/api/bookings/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED')
            ->assertJsonCount(0, 'data.seats');

        // The seats can be booked again right away.
        book($this, ($this->seatIds)('A1', 'A2'))->assertCreated();
    });

    it('refuses to cancel a paid ticket', function () {
        $paid = Booking::factory()->confirmed()->for($this->user)->for($this->showtime)->create();

        expectApiError($this->postJson("/api/bookings/{$paid->id}/cancel"), 422, 'booking_not_cancellable');
        expect($paid->fresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('reports EXPIRED when the hold had already run out', function () {
        $id = book($this, ($this->seatIds)('A1'))->json('data.id');
        $this->travel(11)->minutes();

        $this->postJson("/api/bookings/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'EXPIRED');
    });
});
