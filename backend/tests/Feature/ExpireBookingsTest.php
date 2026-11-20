<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Showtime;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-19 03:00:00', 'UTC'));
    $this->studio = studioWithSeats(rows: 1, seatsPerRow: 3);
    $this->showtime = Showtime::factory()->for($this->studio)->startingAt('2026-11-19 10:00:00')->create();
});

it('expires holds that ran out and frees their seats', function () {
    $seat = $this->studio->seats->first();
    $stale = Booking::factory()->for($this->showtime)->state(['expires_at' => now()->subSecond()])->withSeats($seat)->create();
    $fresh = Booking::factory()->for($this->showtime)->state(['expires_at' => now()->addMinutes(5)])
        ->withSeats($this->studio->seats[1])->create();
    $paid = Booking::factory()->confirmed()->for($this->showtime)->withSeats($this->studio->seats[2])->create();

    $this->artisan('bookings:expire')->expectsOutput('Expired 1 booking(s).')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($fresh->fresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($paid->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and(BookingSeat::where('booking_id', $stale->id)->exists())->toBeFalse();

    // The freed seat can be booked again.
    actingAsCustomer();
    $this->postJson('/api/bookings', ['showtime_id' => $this->showtime->id, 'seat_ids' => [$seat->id]])->assertCreated();
});

it('expires a booking exactly 10 minutes after it was created', function () {
    actingAsCustomer();
    $id = $this->postJson('/api/bookings', [
        'showtime_id' => $this->showtime->id,
        'seat_ids' => [$this->studio->seats->first()->id],
    ])->json('data.id');

    $this->travel(9)->minutes();
    $this->artisan('bookings:expire');
    expect(Booking::find($id)->status)->toBe(BookingStatus::PendingPayment);

    $this->travel(1)->minutes();
    $this->artisan('bookings:expire');
    expect(Booking::find($id)->status)->toBe(BookingStatus::Expired);
});

it('does nothing when run twice', function () {
    Booking::factory()->for($this->showtime)->state(['expires_at' => now()->subMinute()])->create();

    $this->artisan('bookings:expire')->expectsOutput('Expired 1 booking(s).');
    $this->artisan('bookings:expire')->expectsOutput('Expired 0 booking(s).');
});

it('is scheduled every minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'bookings:expire'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
