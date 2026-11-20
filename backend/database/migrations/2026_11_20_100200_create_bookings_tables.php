<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Created in phase 1 because the seat map must already report held/sold seats.
 * The endpoint that creates bookings arrives in phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('showtime_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);                    // App\Enums\BookingStatus
            $table->unsignedInteger('total_idr');
            // Random, long, never sequential. NULL until the booking is paid.
            $table->string('ticket_code', 32)->nullable()->unique();
            $table->dateTime('expires_at')->nullable();      // end of the seat hold
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['showtime_id', 'status']);
        });

        Schema::create('booking_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // Denormalised from bookings so the unique index below can exist.
            $table->foreignId('showtime_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_idr');            // price at booking time

            // Second line of defence against double booking (SPEC 4.2).
            // Rows are deleted when a booking expires or is cancelled.
            $table->unique(['showtime_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_seats');
        Schema::dropIfExists('bookings');
    }
};
