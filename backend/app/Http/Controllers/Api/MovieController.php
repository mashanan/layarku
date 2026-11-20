<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovieStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListMoviesRequest;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovieController extends Controller
{
    public function index(ListMoviesRequest $request): AnonymousResourceCollection
    {
        $status = $request->status();

        $movies = Movie::query()
            ->where('status', $status)
            // Newest releases first while showing; soonest first when upcoming.
            ->orderBy('release_date', $status === MovieStatus::ComingSoon ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate($request->validated('per_page') ?? 20)
            ->withQueryString();

        return MovieResource::collection($movies);
    }

    public function show(Movie $movie): MovieResource
    {
        return new MovieResource($movie);
    }
}
