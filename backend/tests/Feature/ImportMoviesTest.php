<?php

use App\Enums\MovieStatus;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Support\Facades\Http;

/*
| TMDB is never called for real (TestCase::preventStrayRequests). These fakes
| mirror the shape of TMDB's v3 responses for the fields we read.
*/

beforeEach(function () {
    config(['services.tmdb.key' => 'test-key']);
    $this->travelTo(now()->parse('2026-11-19 03:00:00', 'UTC')); // 19 Nov in Jakarta
});

function tmdbDetails(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'title' => "Movie {$id}",
        'overview' => "Overview {$id}",
        'poster_path' => "/poster{$id}.jpg",
        'backdrop_path' => "/backdrop{$id}.jpg",
        'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 18, 'name' => 'Drama']],
        'runtime' => 110,
        'release_date' => '2026-11-01',
        'release_dates' => ['results' => [
            ['iso_3166_1' => 'US', 'release_dates' => [['certification' => 'PG-13', 'type' => 3]]],
            ['iso_3166_1' => 'ID', 'release_dates' => [['certification' => '', 'type' => 1], ['certification' => '13+', 'type' => 3]]],
        ]],
    ], $overrides);
}

/** @param list<int> $nowPlaying @param list<array{id:int, release_date:string}> $upcoming */
function fakeTmdb(array $nowPlaying, array $upcoming, array $details = []): void
{
    Http::fake([
        'api.themoviedb.org/3/movie/now_playing*' => Http::sequence()
            ->push(['page' => 1, 'results' => array_map(fn ($id) => ['id' => $id, 'release_date' => '2026-11-01'], $nowPlaying)])
            ->whenEmpty(Http::response(['page' => 2, 'results' => []])),
        'api.themoviedb.org/3/movie/upcoming*' => Http::sequence()
            ->push(['page' => 1, 'results' => $upcoming])
            ->whenEmpty(Http::response(['page' => 2, 'results' => []])),
        'api.themoviedb.org/3/movie/*' => function ($request) use ($details) {
            $id = (int) basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response($details[$id] ?? tmdbDetails($id));
        },
    ]);
}

it('imports now showing and coming soon movies with their details', function () {
    fakeTmdb(
        nowPlaying: [101, 102],
        upcoming: [
            ['id' => 102, 'release_date' => '2026-12-01'],  // already showing -> stays now_showing
            ['id' => 201, 'release_date' => '2026-12-24'],  // future -> coming soon
            ['id' => 202, 'release_date' => '2026-11-10'],  // already released -> skipped
        ],
        details: [201 => tmdbDetails(201, ['runtime' => 0, 'release_date' => '2026-12-24', 'release_dates' => ['results' => [
            ['iso_3166_1' => 'US', 'release_dates' => [['certification' => 'R', 'type' => 3]]],
        ]]])],
    );

    $this->artisan('movies:import', ['--no-showtimes' => true])->assertSuccessful();

    expect(Movie::where('status', MovieStatus::NowShowing)->pluck('tmdb_id')->sort()->values()->all())->toBe([101, 102])
        ->and(Movie::where('status', MovieStatus::ComingSoon)->pluck('tmdb_id')->all())->toBe([201])
        ->and(Movie::where('tmdb_id', 202)->exists())->toBeFalse();

    $movie = Movie::firstWhere('tmdb_id', 101);
    expect($movie->title)->toBe('Movie 101')
        ->and($movie->genres)->toBe(['Action', 'Drama'])
        ->and($movie->duration_minutes)->toBe(110)
        ->and($movie->age_rating)->toBe('13+')            // Indonesian rating preferred
        ->and($movie->poster_path)->toBe('/poster101.jpg')
        ->and($movie->synced_at)->not->toBeNull();

    $comingSoon = Movie::firstWhere('tmdb_id', 201);
    expect($comingSoon->age_rating)->toBe('R')            // falls back to the US rating
        ->and($comingSoon->duration_minutes)->toBeNull();  // TMDB runtime 0 means unknown

    Http::assertSent(fn ($request) => str_contains($request->url(), 'now_playing')
        && str_contains($request->url(), 'region=ID')
        && str_contains($request->url(), 'api_key=test-key'));
});

it('can be run again without duplicating, and marks dropped movies as ended', function () {
    Movie::factory()->create(['tmdb_id' => 101, 'title' => 'Old title']);
    Movie::factory()->create(['tmdb_id' => 999]); // no longer in TMDB's lists

    fakeTmdb(nowPlaying: [101], upcoming: []);

    $this->artisan('movies:import', ['--no-showtimes' => true])->assertSuccessful();

    expect(Movie::where('tmdb_id', 101)->count())->toBe(1)
        ->and(Movie::firstWhere('tmdb_id', 101)->title)->toBe('Movie 101')
        ->and(Movie::firstWhere('tmdb_id', 999)->status)->toBe(MovieStatus::Ended);
});

it('respects the import limits', function () {
    fakeTmdb(nowPlaying: [1, 2, 3, 4, 5], upcoming: []);

    $this->artisan('movies:import', ['--now-showing' => 3, '--no-showtimes' => true])->assertSuccessful();

    expect(Movie::count())->toBe(3);
});

it('builds the demo schedule after importing', function () {
    studioWithSeats();
    fakeTmdb(nowPlaying: [101], upcoming: []);

    $this->artisan('movies:import', ['--days' => 2])->assertSuccessful();

    expect(Showtime::count())->toBeGreaterThan(0);
});

it('fails with a clear message when the API key is missing', function () {
    config(['services.tmdb.key' => null]);

    $this->artisan('movies:import')
        ->expectsOutputToContain('TMDB_API_KEY is not set')
        ->assertFailed();
});

it('fails without leaking the API key when TMDB rejects it', function () {
    Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'Invalid API key'], 401)]);

    $this->artisan('movies:import')
        ->expectsOutputToContain('HTTP 401 from TMDB (check TMDB_API_KEY)')
        ->doesntExpectOutputToContain('test-key')
        ->assertFailed();
});
