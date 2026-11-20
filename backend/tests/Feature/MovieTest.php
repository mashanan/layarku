<?php

use App\Enums\MovieStatus;
use App\Models\Movie;

it('requires authentication', function () {
    expectApiError($this->getJson('/api/movies'), 401, 'unauthenticated');
});

it('lists now showing movies by default, newest first', function () {
    actingAsCustomer();
    $older = Movie::factory()->create(['release_date' => '2026-10-01']);
    $newer = Movie::factory()->create(['release_date' => '2026-11-01']);
    Movie::factory()->comingSoon()->create();
    Movie::factory()->create(['status' => MovieStatus::Ended]);

    $this->getJson('/api/movies')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('lists coming soon movies, soonest first', function () {
    actingAsCustomer();
    $later = Movie::factory()->comingSoon()->create(['release_date' => '2027-02-01']);
    $sooner = Movie::factory()->comingSoon()->create(['release_date' => '2027-01-01']);
    Movie::factory()->create();

    $this->getJson('/api/movies?status=coming_soon')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $sooner->id)
        ->assertJsonPath('data.1.id', $later->id);
});

it('does not expose ended movies as a filter', function () {
    actingAsCustomer();

    expectApiError($this->getJson('/api/movies?status=ended'), 422, 'validation_failed');
});

it('paginates', function () {
    actingAsCustomer();
    Movie::factory()->count(5)->create();

    $this->getJson('/api/movies?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3);
});

it('shows movie details with full image URLs', function () {
    actingAsCustomer();
    $movie = Movie::factory()->create([
        'poster_path' => '/abc.jpg',
        'backdrop_path' => null,
        'genres' => ['Action', 'Comedy'],
        'duration_minutes' => 117,
        'age_rating' => '13+',
    ]);

    $this->getJson("/api/movies/{$movie->id}")
        ->assertOk()
        ->assertJsonPath('data.poster_url', 'https://image.tmdb.org/t/p/w500/abc.jpg')
        ->assertJsonPath('data.backdrop_url', null)
        ->assertJsonPath('data.genres', ['Action', 'Comedy'])
        ->assertJsonPath('data.duration_minutes', 117)
        ->assertJsonPath('data.age_rating', '13+')
        ->assertJsonPath('data.status', 'now_showing');
});

it('returns a clean 404 for an unknown movie', function () {
    actingAsCustomer();

    $response = $this->getJson('/api/movies/999999');

    expectApiError($response, 404, 'not_found');
    // The internal model class name must not leak.
    expect($response->getContent())->not->toContain('App\\\\Models');
});
