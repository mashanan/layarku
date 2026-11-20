<?php

namespace App\Console\Commands;

use App\Actions\Demo\SeedSampleBookings;
use App\Actions\Movies\ImportMoviesFromTmdb;
use App\Actions\Showtimes\CreateDemoShowtimes;
use App\Services\Tmdb\TmdbException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('movies:import
    {--now-showing=12 : How many "now playing" movies to import}
    {--coming-soon=8 : How many upcoming movies to import}
    {--days= : Days of demo showtimes to generate (default from config/cinema.php)}
    {--no-showtimes : Only import movies, do not touch the schedule}')]
#[Description('Import movies from TMDB, then build the demo schedule and sample bookings')]
class ImportMovies extends Command
{
    public function handle(
        ImportMoviesFromTmdb $import,
        CreateDemoShowtimes $createShowtimes,
        SeedSampleBookings $seedBookings,
    ): int {
        $this->info('Importing movies from TMDB...');

        try {
            $result = $import((int) $this->option('now-showing'), (int) $this->option('coming-soon'));
        } catch (TmdbException|RequestException|ConnectionException $e) {
            // Show a useful message, but never echo the request URL: it contains the API key.
            $this->error('TMDB import failed: '.$this->safeMessage($e));

            return self::FAILURE;
        }

        $this->table(['Now showing', 'Coming soon', 'Marked ended'], [array_values($result)]);

        if ($this->option('no-showtimes')) {
            return self::SUCCESS;
        }

        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $showtimes = $createShowtimes($days);
        $bookings = $seedBookings();

        $this->info("Created {$showtimes} showtimes and {$bookings} sample bookings.");

        return self::SUCCESS;
    }

    private function safeMessage(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return "HTTP {$e->response->status()} from TMDB".
                ($e->response->status() === 401 ? ' (check TMDB_API_KEY)' : '');
        }

        return $e instanceof ConnectionException ? 'could not reach TMDB (network problem)' : $e->getMessage();
    }
}
