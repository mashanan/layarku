<?php

namespace App\Http\Resources;

use App\Models\Showtime;
use App\Support\ApiTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Showtime */
class ShowtimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => ApiTime::format($this->starts_at),
            'ends_at' => ApiTime::format($this->ends_at),
            'price_idr' => $this->price_idr,
            // Computed by the server so the app never disagrees with the
            // booking rule (closes 15 minutes before the start).
            'is_bookable' => $this->isBookable(),
            'booking_closes_at' => ApiTime::format($this->bookingClosesAt()),
            'studio' => $this->whenLoaded('studio', fn () => [
                'id' => $this->studio->id,
                'name' => $this->studio->name,
            ]),
        ];
    }
}
