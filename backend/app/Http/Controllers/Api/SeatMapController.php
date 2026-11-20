<?php

namespace App\Http\Controllers\Api;

use App\Actions\Seats\GetSeatMap;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeatMapResource;
use App\Models\Showtime;

class SeatMapController extends Controller
{
    public function show(Showtime $showtime, GetSeatMap $getSeatMap): SeatMapResource
    {
        $showtime->load('movie', 'studio.cinema');

        return new SeatMapResource($showtime, $getSeatMap($showtime));
    }
}
