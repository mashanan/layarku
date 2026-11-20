<?php

namespace App\Models;

use App\Enums\MovieStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tmdb_id', 'title', 'overview', 'poster_path', 'backdrop_path', 'genres',
    'duration_minutes', 'age_rating', 'release_date', 'status', 'synced_at',
])]
class Movie extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'release_date' => 'date',
            'status' => MovieStatus::class,
            'synced_at' => 'datetime',
        ];
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    /** Full image URL on TMDB's CDN, or null when TMDB has no image. */
    public static function imageUrl(?string $path, string $size): ?string
    {
        return $path ? config('services.tmdb.image_base_url')."/{$size}{$path}" : null;
    }
}
