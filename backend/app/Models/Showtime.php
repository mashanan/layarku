<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['movie_id', 'studio_id', 'starts_at', 'ends_at', 'price_idr'])]
class Showtime extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price_idr' => 'integer',
        ];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** The moment sales stop: N minutes before the movie starts (SPEC 4.1). */
    public function bookingClosesAt(): Carbon
    {
        return $this->starts_at->copy()->subMinutes(config('cinema.booking_closes_minutes_before'));
    }

    public function isBookable(?Carbon $now = null): bool
    {
        return ($now ?? now())->lt($this->bookingClosesAt());
    }
}
