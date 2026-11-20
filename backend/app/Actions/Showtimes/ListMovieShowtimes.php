<?php

namespace App\Actions\Showtimes;

use App\Models\Movie;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Showtimes of one movie on one *local* calendar date.
 *
 * "21 November" means 21 November in Jakarta, i.e. 20 Nov 17:00 UTC up to
 * 21 Nov 16:59:59 UTC. Doing this conversion on the server keeps every
 * client from having to get time zones right.
 */
class ListMovieShowtimes
{
    /** @return Collection<int, Showtime> */
    public function __invoke(Movie $movie, CarbonImmutable $localDate, ?int $cinemaId = null): Collection
    {
        $dayStart = $localDate->startOfDay();

        return Showtime::query()
            ->where('movie_id', $movie->id)
            ->whereBetween('starts_at', [$dayStart->utc(), $dayStart->endOfDay()->utc()])
            ->when($cinemaId, fn ($q) => $q->whereHas('studio', fn ($s) => $s->where('cinema_id', $cinemaId)))
            ->with('studio.cinema')
            ->orderBy('starts_at')
            ->get();
    }
}
