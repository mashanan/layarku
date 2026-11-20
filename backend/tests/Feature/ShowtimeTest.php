<?php

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;

beforeEach(function () {
    actingAsCustomer();
    // Thursday 19 Nov 2026, 10:00 in Jakarta (UTC+7).
    $this->travelTo(now()->parse('2026-11-19 03:00:00', 'UTC'));
    $this->movie = Movie::factory()->create();
});

it('uses the Jakarta calendar date, not the UTC one', function () {
    $studio = Studio::factory()->create();
    // 21 Nov 23:30 Jakarta = 21 Nov 16:30 UTC -> belongs to the 21st.
    $lateShow = Showtime::factory()->for($this->movie)->for($studio)->startingAt('2026-11-21 16:30:00')->create();
    // 22 Nov 00:30 Jakarta = 21 Nov 17:30 UTC -> belongs to the 22nd, although it is still the 21st in UTC.
    $midnightShow = Showtime::factory()->for($this->movie)->for($studio)->startingAt('2026-11-21 17:30:00')->create();

    $this->getJson("/api/movies/{$this->movie->id}/showtimes?date=2026-11-21")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-11-21')
        ->assertJsonPath('data.timezone', 'Asia/Jakarta')
        ->assertJsonPath('data.cinemas.0.showtimes.0.id', $lateShow->id)
        ->assertJsonCount(1, 'data.cinemas.0.showtimes');

    $this->getJson("/api/movies/{$this->movie->id}/showtimes?date=2026-11-22")
        ->assertJsonPath('data.cinemas.0.showtimes.0.id', $midnightShow->id);
});

it('defaults to today in Jakarta', function () {
    $show = Showtime::factory()->for($this->movie)->startingAt('2026-11-19 12:00:00')->create();
    Showtime::factory()->for($this->movie)->startingAt('2026-11-20 12:00:00')->create();

    $this->getJson("/api/movies/{$this->movie->id}/showtimes")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-11-19')
        ->assertJsonPath('data.cinemas.0.showtimes.0.id', $show->id)
        ->assertJsonCount(1, 'data.cinemas.0.showtimes');
});

it('groups showtimes by cinema and includes the studio', function () {
    $bandung = Cinema::factory()->create(['name' => 'Layar Kota Cinema Bandung']);
    $jakarta = Cinema::factory()->create(['name' => 'Layar Kota Cinema Jakarta']);
    $studioB = Studio::factory()->for($bandung)->create(['name' => 'Studio 1']);
    $studioJ = Studio::factory()->for($jakarta)->create(['name' => 'Studio 2']);

    Showtime::factory()->for($this->movie)->for($studioJ)->startingAt('2026-11-20 05:00:00')->create();
    Showtime::factory()->for($this->movie)->for($studioB)->startingAt('2026-11-20 06:00:00')->create();
    Showtime::factory()->for($this->movie)->for($studioB)->startingAt('2026-11-20 09:00:00')->create();

    $this->getJson("/api/movies/{$this->movie->id}/showtimes?date=2026-11-20")
        ->assertOk()
        ->assertJsonCount(2, 'data.cinemas')
        ->assertJsonPath('data.cinemas.0.name', 'Layar Kota Cinema Bandung')
        ->assertJsonCount(2, 'data.cinemas.0.showtimes')
        ->assertJsonPath('data.cinemas.0.showtimes.0.studio.name', 'Studio 1')
        ->assertJsonPath('data.cinemas.0.showtimes.0.starts_at', '2026-11-20T06:00:00Z')
        ->assertJsonPath('data.cinemas.1.name', 'Layar Kota Cinema Jakarta');
});

it('filters by cinema', function () {
    $wanted = Studio::factory()->create();
    $other = Studio::factory()->create();
    Showtime::factory()->for($this->movie)->for($wanted)->startingAt('2026-11-20 05:00:00')->create();
    Showtime::factory()->for($this->movie)->for($other)->startingAt('2026-11-20 05:00:00')->create();

    $this->getJson("/api/movies/{$this->movie->id}/showtimes?date=2026-11-20&cinema_id={$wanted->cinema_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.cinemas')
        ->assertJsonPath('data.cinemas.0.id', $wanted->cinema_id);
});

it('only returns showtimes of the requested movie', function () {
    Showtime::factory()->startingAt('2026-11-20 05:00:00')->create(); // another movie

    $this->getJson("/api/movies/{$this->movie->id}/showtimes?date=2026-11-20")
        ->assertOk()
        ->assertJsonCount(0, 'data.cinemas');
});

it('closes booking 15 minutes before the start', function () {
    // now = 03:00 UTC
    $closingSoon = Showtime::factory()->for($this->movie)->startingAt('2026-11-19 03:14:00')->create();
    $stillOpen = Showtime::factory()->for($this->movie)->startingAt('2026-11-19 03:16:00')->create();

    $response = $this->getJson("/api/movies/{$this->movie->id}/showtimes?date=2026-11-19")->assertOk();
    $showtimes = collect($response->json('data.cinemas'))->flatMap(fn ($c) => $c['showtimes'])->keyBy('id');

    expect($showtimes[$closingSoon->id]['is_bookable'])->toBeFalse()
        ->and($showtimes[$stillOpen->id]['is_bookable'])->toBeTrue()
        ->and($showtimes[$stillOpen->id]['booking_closes_at'])->toBe('2026-11-19T03:01:00Z');
});

it('validates the date format and cinema', function () {
    expectApiError($this->getJson("/api/movies/{$this->movie->id}/showtimes?date=21-11-2026"), 422, 'validation_failed');
    expectApiError($this->getJson("/api/movies/{$this->movie->id}/showtimes?cinema_id=999999"), 422, 'validation_failed');
});
