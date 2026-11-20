<?php

namespace App\Services\Tmdb;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the TMDB v3 API. Only the three calls the importer
 * needs. Tests replace the network with Http::fake(), so no test ever
 * talks to TMDB.
 */
class TmdbClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $region,
        private readonly string $language,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.tmdb.key'),
            config('services.tmdb.base_url'),
            config('services.tmdb.region'),
            config('services.tmdb.language'),
        );
    }

    /** @return list<array<string, mixed>> */
    public function nowPlaying(int $page = 1): array
    {
        return $this->get('/movie/now_playing', ['region' => $this->region, 'page' => $page])['results'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function upcoming(int $page = 1): array
    {
        return $this->get('/movie/upcoming', ['region' => $this->region, 'page' => $page])['results'] ?? [];
    }

    /** Movie details including per-country certifications (age ratings). */
    public function movie(int $tmdbId): array
    {
        return $this->get("/movie/{$tmdbId}", ['append_to_response' => 'release_dates']);
    }

    private function get(string $path, array $query): array
    {
        return $this->request()->get($path, $query)->throw()->json();
    }

    private function request(): PendingRequest
    {
        if (blank($this->apiKey)) {
            throw new TmdbException('TMDB_API_KEY is not set. Add it to backend/.env.');
        }

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(15)
            // Retry transient failures (timeouts, 5xx, 429) but not 401/404.
            ->retry(2, 500, fn ($e) => ! $e instanceof RequestException
                || $e->response->serverError() || $e->response->status() === 429, throw: false)
            ->withQueryParameters(['api_key' => $this->apiKey, 'language' => $this->language]);
    }
}
