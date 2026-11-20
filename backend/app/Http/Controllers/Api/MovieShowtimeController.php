<?php

namespace App\Http\Controllers\Api;

use App\Actions\Showtimes\ListMovieShowtimes;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListShowtimesRequest;
use App\Http\Resources\MovieScheduleResource;
use App\Models\Movie;

class MovieShowtimeController extends Controller
{
    public function index(ListShowtimesRequest $request, Movie $movie, ListMovieShowtimes $listShowtimes): MovieScheduleResource
    {
        $date = $request->localDate();

        return new MovieScheduleResource($date, $listShowtimes($movie, $date, $request->cinemaId()));
    }
}
