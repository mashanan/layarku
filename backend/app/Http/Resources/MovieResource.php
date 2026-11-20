<?php

namespace App\Http\Resources;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Movie */
class MovieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'overview' => $this->overview,
            // Ready-to-load URLs on TMDB's CDN, so the app doesn't need to know
            // TMDB's image URL scheme. w500 suits phone posters.
            'poster_url' => Movie::imageUrl($this->poster_path, 'w500'),
            'backdrop_url' => Movie::imageUrl($this->backdrop_path, 'w780'),
            'genres' => $this->genres,
            'duration_minutes' => $this->duration_minutes,
            'age_rating' => $this->age_rating,
            'release_date' => $this->release_date?->toDateString(),
            'status' => $this->status->value,
        ];
    }
}
