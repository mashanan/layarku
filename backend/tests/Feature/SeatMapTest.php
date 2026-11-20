<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Showtime;

beforeEach(function () {
    actingAsCustomer();
    // 2 rows x 4 seats, aisle after seat 2: A1 A2 _ A3 A4
    $this->studio = studioWithSeats(rows: 2, seatsPerRow: 4, aisleAfter: [2]);
    $this->showtime = Showtime::factory()->for($this->studio)->create();
    $this->seat = fn (string $label) => $this->studio->seats->first(fn ($s) => $s->label() === $label);
});

function seatStatuses($response): array
{
    return collect($response->json('data.seats'))->pluck('status', 'label')->all();
}

it('returns every seat as available when nothing is booked', function () {
    $response = $this->getJson("/api/showtimes/{$this->showtime->id}/seats")->assertOk();

    expect(seatStatuses($response))->toBe([
        'A1' => 'available', 'A2' => 'available', 'A3' => 'available', 'A4' => 'available',
        'B1' => 'available', 'B2' => 'available', 'B3' => 'available', 'B4' => 'available',
    ]);
});

it('describes the grid including the aisle', function () {
    $this->getJson("/api/showtimes/{$this->showtime->id}/seats")
        ->assertOk()
        ->assertJsonPath('data.grid', ['columns' => 5, 'rows' => 2])
        ->assertJsonPath('data.seats.2.label', 'A3')
        ->assertJsonPath('data.seats.2.grid_x', 3) // grid_x 2 is the aisle
        ->assertJsonPath('data.studio.id', $this->studio->id)
        ->assertJsonPath('data.showtime.id', $this->showtime->id);
});

it('marks seats of paid and used bookings as sold', function () {
    Booking::factory()->confirmed()->for($this->showtime)->withSeats(($this->seat)('A1'), ($this->seat)('A2'))->create();
    Booking::factory()->for($this->showtime)
        ->state(['status' => BookingStatus::Used, 'expires_at' => null, 'used_at' => now()])
        ->withSeats(($this->seat)('B4'))->create();

    $statuses = seatStatuses($this->getJson("/api/showtimes/{$this->showtime->id}/seats"));

    expect($statuses['A1'])->toBe('sold')
        ->and($statuses['A2'])->toBe('sold')
        ->and($statuses['B4'])->toBe('sold')
        ->and($statuses['A3'])->toBe('available');
});

it('marks seats of an unpaid booking as held while the hold lasts', function () {
    Booking::factory()->for($this->showtime)
        ->state(['expires_at' => now()->addMinutes(5)])
        ->withSeats(($this->seat)('A3'))->create();

    expect(seatStatuses($this->getJson("/api/showtimes/{$this->showtime->id}/seats"))['A3'])->toBe('held');
});

it('treats an unpaid booking whose hold ran out as available', function () {
    Booking::factory()->for($this->showtime)
        ->state(['expires_at' => now()->subMinute()])
        ->withSeats(($this->seat)('A3'))->create();

    expect(seatStatuses($this->getJson("/api/showtimes/{$this->showtime->id}/seats"))['A3'])->toBe('available');
});

it('ignores bookings for other showtimes of the same studio', function () {
    $otherShowtime = Showtime::factory()->for($this->studio)->startingAt(now()->addDays(2))->create();
    Booking::factory()->confirmed()->for($otherShowtime)->withSeats(($this->seat)('A1'))->create();

    expect(seatStatuses($this->getJson("/api/showtimes/{$this->showtime->id}/seats"))['A1'])->toBe('available');
});

it('returns a clean 404 for an unknown showtime', function () {
    expectApiError($this->getJson('/api/showtimes/999999/seats'), 404, 'not_found');
});
