<?php

namespace App\Actions\Movies;

use App\Enums\MovieStatus;
use App\Models\Movie;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\Collection;

/**
 * Pulls "now playing" and "upcoming" movies for one region from TMDB and
 * upserts them by tmdb_id. Safe to run again at any time:
 *
 *  - existing rows are refreshed (TMDB allows caching for at most 6 months,
 *    so a scheduled re-import keeps us within the terms);
 *  - movies that dropped out of both lists become `ended` instead of being
 *    deleted, because bookings may still point at them.
 */
class ImportMoviesFromTmdb
{
    /** Certification countries to try, in order. */
    private const RATING_COUNTRIES = ['ID', 'US'];

    private const MAX_PAGES = 3;

    public function __construct(private readonly TmdbClient $tmdb) {}

    /**
     * @return array{now_showing: int, coming_soon: int, ended: int}
     */
    public function __invoke(int $nowShowingLimit = 12, int $comingSoonLimit = 8): array
    {
        $today = now(config('cinema.timezone'))->toDateString();

        $nowShowingIds = $this->collectIds(
            fn (int $page) => $this->tmdb->nowPlaying($page),
            $nowShowingLimit,
        );

        // TMDB's "upcoming" list also contains films already released, so keep
        // only future releases that are not already showing.
        $comingSoonIds = $this->collectIds(
            fn (int $page) => array_filter(
                $this->tmdb->upcoming($page),
                fn (array $m) => ($m['release_date'] ?? '') > $today && ! $nowShowingIds->contains($m['id']),
            ),
            $comingSoonLimit,
        );

        foreach ($nowShowingIds as $id) {
            $this->upsert($id, MovieStatus::NowShowing);
        }
        foreach ($comingSoonIds as $id) {
            $this->upsert($id, MovieStatus::ComingSoon);
        }

        $ended = Movie::query()
            ->whereIn('status', [MovieStatus::NowShowing, MovieStatus::ComingSoon])
            ->whereNotIn('tmdb_id', $nowShowingIds->merge($comingSoonIds))
            ->update(['status' => MovieStatus::Ended]);

        return [
            'now_showing' => $nowShowingIds->count(),
            'coming_soon' => $comingSoonIds->count(),
            'ended' => $ended,
        ];
    }

    /** Walk result pages until we have enough unique ids. */
    private function collectIds(callable $fetchPage, int $limit): Collection
    {
        $ids = collect();

        for ($page = 1; $page <= self::MAX_PAGES && $ids->count() < $limit; $page++) {
            $results = $fetchPage($page);
            if ($results === []) {
                break;
            }
            $ids = $ids->merge(array_column($results, 'id'))->unique()->values();
        }

        return $ids->take($limit)->values();
    }

    private function upsert(int $tmdbId, MovieStatus $status): void
    {
        $m = $this->tmdb->movie($tmdbId);

        Movie::updateOrCreate(['tmdb_id' => $tmdbId], [
            'title' => $m['title'],
            'overview' => $m['overview'] ?: null,
            'poster_path' => $m['poster_path'] ?? null,
            'backdrop_path' => $m['backdrop_path'] ?? null,
            'genres' => array_column($m['genres'] ?? [], 'name'),
            'duration_minutes' => ($m['runtime'] ?? 0) ?: null,
            'age_rating' => $this->ageRating($m['release_dates']['results'] ?? []),
            'release_date' => ($m['release_date'] ?? '') ?: null,
            'status' => $status,
            'synced_at' => now(),
        ]);
    }

    /** First non-empty certification for Indonesia, falling back to the US. */
    private function ageRating(array $releaseDatesByCountry): ?string
    {
        $byCountry = collect($releaseDatesByCountry)->keyBy('iso_3166_1');

        foreach (self::RATING_COUNTRIES as $country) {
            $rating = collect($byCountry[$country]['release_dates'] ?? [])
                ->pluck('certification')
                ->first(fn ($c) => filled($c));

            if ($rating) {
                return $rating;
            }
        }

        return null;
    }
}
