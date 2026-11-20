<?php

use App\Actions\Demo\SeedSampleBookings;
use App\Actions\Seats\GenerateStudioSeats;
use App\Actions\Showtimes\CreateDemoShowtimes;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Studio;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;

describe('seat layout', function () {
    it('leaves an empty grid column for each aisle', function () {
        $studio = Studio::factory()->create();

        $count = app(GenerateStudioSeats::class)($studio, rows: 2, seatsPerRow: 6, aisleAfter: [2, 4]);

        expect($count)->toBe(12);
        $rowA = Seat::where('studio_id', $studio->id)->where('row_label', 'A')->orderBy('number')->pluck('grid_x')->all();
        // seats: 1 2 | 3 4 | 5 6
        expect($rowA)->toBe([0, 1, 3, 4, 6, 7]);
        expect(Seat::where('studio_id', $studio->id)->where('row_label', 'B')->value('grid_y'))->toBe(1);
    });
});

describe('demo schedule', function () {
    beforeEach(function () {
        // Thursday 19 Nov 2026 in Jakarta.
        $this->travelTo(now()->parse('2026-11-19 01:00:00', 'UTC'));
        $this->studio = Studio::factory()->create();
    });

    it('schedules shows between the first and last start time in Jakarta time', function () {
        Movie::factory()->count(2)->create(['duration_minutes' => 100]);

        $created = app(CreateDemoShowtimes::class)(days: 1);

        $starts = Showtime::orderBy('starts_at')->get()
            ->map(fn ($s) => $s->starts_at->copy()->setTimezone('Asia/Jakarta')->format('H:i'))->all();

        // 100 min + 30 min cleaning, rounded up to a quarter hour = 2h15m apart.
        expect($starts)->toBe(['10:00', '12:15', '14:30', '16:45', '19:00', '21:15'])
            ->and($created)->toBe(6);
    });

    it('never overlaps shows in the same studio and rotates movies', function () {
        Movie::factory()->count(3)->create(['duration_minutes' => 150]);

        app(CreateDemoShowtimes::class)(days: 1);

        $shows = Showtime::orderBy('starts_at')->get();
        foreach ($shows->sliding(2) as $pair) {
            [$previous, $next] = $pair->values()->all();
            expect($next->starts_at->gte($previous->ends_at))->toBeTrue();
        }
        expect($shows->pluck('movie_id')->unique()->count())->toBe(3);
    });

    it('charges weekend prices from Friday to Sunday', function () {
        Movie::factory()->create();

        app(CreateDemoShowtimes::class)(days: 3); // Thu, Fri, Sat

        $priceByDay = Showtime::get()->groupBy(fn ($s) => $s->starts_at->copy()->setTimezone('Asia/Jakarta')->format('D'))
            ->map(fn ($shows) => $shows->first()->price_idr);

        expect($priceByDay['Thu'])->toBe(40000)
            ->and($priceByDay['Fri'])->toBe(50000)
            ->and($priceByDay['Sat'])->toBe(50000);
    });

    it('does not duplicate shows when run again', function () {
        Movie::factory()->create();

        $first = app(CreateDemoShowtimes::class)(days: 2);
        $second = app(CreateDemoShowtimes::class)(days: 2);

        expect($first)->toBeGreaterThan(0)
            ->and($second)->toBe(0)
            ->and(Showtime::count())->toBe($first);
    });

    it('only schedules movies that are now showing', function () {
        Movie::factory()->comingSoon()->create();

        expect(app(CreateDemoShowtimes::class)(days: 1))->toBe(0);
    });
});

describe('seeders', function () {
    it('creates the fictional cinemas, seat layouts and demo accounts', function () {
        $this->seed();

        expect(Studio::count())->toBe(4)
            ->and(Seat::count())->toBe(356)
            ->and(User::firstWhere('email', DemoUserSeeder::CUSTOMER_EMAIL)->role)->toBe(UserRole::Customer)
            ->and(User::firstWhere('email', DemoUserSeeder::STAFF_EMAIL)->role)->toBe(UserRole::Staff);
    });

    it('adds sample bookings that respect the 6-seat rule and are idempotent', function () {
        $this->seed();
        $movie = Movie::factory()->create();
        $showtime = Showtime::factory()->for($movie)->for(Studio::first())->create();

        $created = app(SeedSampleBookings::class)();
        $again = app(SeedSampleBookings::class)();

        $customer = User::firstWhere('email', DemoUserSeeder::CUSTOMER_EMAIL);
        expect($created)->toBeGreaterThan(1)
            ->and($again)->toBe(0)
            ->and(Booking::where('user_id', $customer->id)->count())->toBe(1)
            ->and(Booking::where('status', '!=', BookingStatus::Confirmed)->count())->toBe(0)
            ->and(Booking::whereNull('ticket_code')->count())->toBe(0);

        $maxSeats = BookingSeat::selectRaw('booking_id, count(*) as c')->groupBy('booking_id')->get()->max('c');
        expect($maxSeats)->toBeLessThanOrEqual(6)
            ->and(BookingSeat::where('showtime_id', $showtime->id)->count())->toBe((int) floor(96 * 0.3) + 2);
    });
});
