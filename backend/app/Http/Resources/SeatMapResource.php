<?php

namespace App\Http\Resources;

use App\Enums\SeatStatus;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Seat map for one showtime. `grid` gives the drawing area so the client can
 * size its canvas before placing seats at (grid_x, grid_y). Row A
 * (grid_y = 0) is nearest to the screen.
 */
class SeatMapResource extends JsonResource
{
    /** @param Collection<int, array{seat: Seat, status: SeatStatus}> $seats */
    public function __construct(Showtime $showtime, private readonly Collection $seats)
    {
        parent::__construct($showtime);
    }

    public function toArray(Request $request): array
    {
        /** @var Showtime $showtime */
        $showtime = $this->resource;

        return [
            'showtime' => new ShowtimeResource($showtime),
            'movie' => ['id' => $showtime->movie->id, 'title' => $showtime->movie->title],
            'cinema' => ['id' => $showtime->studio->cinema->id, 'name' => $showtime->studio->cinema->name],
            'studio' => ['id' => $showtime->studio->id, 'name' => $showtime->studio->name],
            'grid' => [
                'columns' => $this->seats->max(fn ($s) => $s['seat']->grid_x) + 1,
                'rows' => $this->seats->max(fn ($s) => $s['seat']->grid_y) + 1,
            ],
            'seats' => $this->seats->map(fn ($s) => [
                'id' => $s['seat']->id,
                'label' => $s['seat']->label(),
                'row' => $s['seat']->row_label,
                'number' => $s['seat']->number,
                'grid_x' => $s['seat']->grid_x,
                'grid_y' => $s['seat']->grid_y,
                'status' => $s['status']->value,
            ])->values(),
        ];
    }
}
