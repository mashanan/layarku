<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Support\ApiTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the app needs to show (and cache offline) one booking/ticket.
 * Expects showtime.movie, showtime.studio.cinema and seats.seat loaded.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $showtime = $this->showtime;
        $isTicket = in_array($this->status, BookingStatus::soldStatuses(), true);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'total_idr' => $this->total_idr,
            // Only meaningful while PENDING_PAYMENT: drives the countdown in the app.
            'expires_at' => $this->status === BookingStatus::PendingPayment ? ApiTime::format($this->expires_at) : null,
            // A paid ticket for a show that has ended is displayed as "expired"
            // by the app; there is no extra database status for it (SPEC 4.3).
            'is_past' => $showtime->ends_at->isPast(),
            // Only paid tickets have a code; it is what the QR encodes.
            'ticket_code' => $isTicket ? $this->ticket_code : null,
            'used_at' => ApiTime::format($this->used_at),
            'created_at' => ApiTime::format($this->created_at),
            'showtime' => [
                'id' => $showtime->id,
                'starts_at' => ApiTime::format($showtime->starts_at),
                'ends_at' => ApiTime::format($showtime->ends_at),
                'price_idr' => $showtime->price_idr,
            ],
            'movie' => [
                'id' => $showtime->movie->id,
                'title' => $showtime->movie->title,
                'poster_url' => Movie::imageUrl($showtime->movie->poster_path, 'w500'),
                'age_rating' => $showtime->movie->age_rating,
            ],
            'cinema' => [
                'id' => $showtime->studio->cinema->id,
                'name' => $showtime->studio->cinema->name,
                'city' => $showtime->studio->cinema->city,
            ],
            'studio' => ['id' => $showtime->studio->id, 'name' => $showtime->studio->name],
            // Seats of expired/cancelled bookings were released, so this is empty for them.
            'seats' => $this->seats
                ->sortBy(fn (BookingSeat $bs) => [$bs->seat->row_label, $bs->seat->number])
                ->map(fn (BookingSeat $bs) => [
                    'id' => $bs->seat->id,
                    'label' => $bs->seat->label(),
                    'price_idr' => $bs->price_idr,
                ])
                ->values(),
        ];
    }
}
