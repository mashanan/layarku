<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListBookingsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Ownership: bookings are always looked up through `$request->user()->bookings()`.
 * Someone else's booking is therefore simply "not found" (404), which also
 * avoids confirming that a given booking id exists.
 */
class BookingController extends Controller
{
    private const RELATIONS = ['showtime.movie', 'showtime.studio.cinema', 'seats.seat'];

    public function index(ListBookingsRequest $request): AnonymousResourceCollection
    {
        $bookings = $request->user()->bookings()
            ->with(self::RELATIONS)
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->validated('per_page') ?? 20);

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $booking = $createBooking(
            $request->user(),
            (int) $request->validated('showtime_id'),
            $request->validated('seat_ids'),
        );

        return (new BookingResource($booking->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $booking): BookingResource
    {
        return new BookingResource($this->findOwned($request, $booking));
    }

    public function cancel(Request $request, int $booking, CancelBooking $cancelBooking): BookingResource
    {
        $cancelled = $cancelBooking($this->findOwned($request, $booking));

        return new BookingResource($cancelled->load(self::RELATIONS));
    }

    private function findOwned(Request $request, int $id): Booking
    {
        return $request->user()->bookings()->with(self::RELATIONS)->findOrFail($id);
    }
}
