<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\MovieShowtimeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SeatMapController;
use Illuminate\Support\Facades\Route;

/*
| All routes are prefixed with /api. Everything except register/login needs
| `Authorization: Bearer <token>` (SPEC section 6).
*/

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [ProfileController::class, 'show']);
    Route::patch('me', [ProfileController::class, 'update']);

    Route::get('movies', [MovieController::class, 'index']);
    Route::get('movies/{movie}', [MovieController::class, 'show']);
    Route::get('movies/{movie}/showtimes', [MovieShowtimeController::class, 'index']);

    Route::get('showtimes/{showtime}/seats', [SeatMapController::class, 'show']);

    Route::get('bookings', [BookingController::class, 'index']);
    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:bookings');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])->whereNumber('booking');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->whereNumber('booking');
});
