<?php

namespace App\Actions\Showtimes;

use App\Enums\MovieStatus;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lays out a believable schedule for the next N days.
 *
 * Every studio runs shows back to back from `first_show` until the last start
 * time, with a cleaning gap, rounded up to a quarter hour (local time).
 * Movies rotate per slot so each film gets screenings in several studios.
 *
 * Times are computed in the cinema time zone (that is how a real schedule is
 * planned: "10:00 in Jakarta") and then stored as UTC.
 *
 * Idempotent per studio and day: a studio-day that already has showtimes is
 * left alone, so running the import again never duplicates screenings.
 */
class CreateDemoShowtimes
{
    public function __invoke(?int $days = null): int
    {
        $config = config('cinema.demo_schedule');
        $days ??= $config['days'];
        $timezone = config('cinema.timezone');

        $movies = Movie::where('status', MovieStatus::NowShowing)->orderBy('id')->get();
        $studios = Studio::orderBy('id')->get();

        if ($movies->isEmpty() || $studios->isEmpty()) {
            return 0;
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();
        $created = 0;

        for ($day = 0; $day < $days; $day++) {
            $date = $today->addDays($day);

            foreach ($studios->values() as $studioIndex => $studio) {
                if ($this->studioHasShowsOn($studio, $date)) {
                    continue;
                }
                $created += $this->scheduleStudioDay($studio, $date, $movies, $studioIndex + $day, $config);
            }
        }

        return $created;
    }

    private function scheduleStudioDay(Studio $studio, CarbonImmutable $date, Collection $movies, int $offset, array $config): int
    {
        $start = $date->setTimeFromTimeString($config['first_show']);
        $lastStart = $date->setTimeFromTimeString($config['last_show_start']);
        // Friday to Sunday are weekend prices.
        $price = $date->dayOfWeekIso >= 5 ? $config['price_weekend_idr'] : $config['price_weekday_idr'];

        $slot = 0;
        while ($start->lte($lastStart)) {
            $movie = $movies[($offset + $slot) % $movies->count()];
            $duration = $movie->duration_minutes ?? 120;

            Showtime::create([
                'movie_id' => $movie->id,
                'studio_id' => $studio->id,
                'starts_at' => $start->utc(),
                'ends_at' => $start->addMinutes($duration)->utc(),
                'price_idr' => $price,
            ]);

            $start = $this->roundUp($start->addMinutes($duration + $config['cleaning_minutes']), $config['slot_rounding_minutes']);
            $slot++;
        }

        return $slot;
    }

    private function studioHasShowsOn(Studio $studio, CarbonImmutable $localDate): bool
    {
        return Showtime::where('studio_id', $studio->id)
            ->whereBetween('starts_at', [$localDate->utc(), $localDate->endOfDay()->utc()])
            ->exists();
    }

    private function roundUp(CarbonImmutable $time, int $minutes): CarbonImmutable
    {
        $remainder = $time->minute % $minutes;

        return $remainder === 0 ? $time : $time->addMinutes($minutes - $remainder);
    }
}
