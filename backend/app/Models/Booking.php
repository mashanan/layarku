<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// `status` is fillable so a booking can be *created* with its initial status.
// Every later change goes through App\Actions\Bookings\BookingStateMachine.
#[Fillable(['user_id', 'showtime_id', 'status', 'total_idr', 'ticket_code', 'expires_at', 'used_at'])]
#[Hidden(['ticket_code'])]
class Booking extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'total_idr' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(BookingSeat::class);
    }
}
