# Layarku backend

Laravel REST API: the source of truth for movies, showtimes, seats, bookings, payments and tickets.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`
- Composer
- MySQL 8 (or PostgreSQL). SQLite is **not** supported, because booking relies on row locks.
- A [TMDB](https://www.themoviedb.org/settings/api) v3 API key

On Windows, [Laragon](https://laragon.org) provides all of the above.

## Setup

```bash
composer install
cp .env.example .env          # then fill in DB_* and TMDB_API_KEY
php artisan key:generate

# create two databases: layarku (development) and layarku_test (tests)
php artisan migrate:fresh --seed   # cinemas, studios, seats, demo accounts
php artisan movies:import          # movies from TMDB + demo schedule + sample bookings
php artisan serve                  # http://localhost:8000 (emulator: http://10.0.2.2:8000)
php artisan schedule:work          # in a second terminal: expires unpaid bookings every minute
```

`movies:import` is safe to run again: it refreshes existing movies, marks movies that left TMDB's lists as `ended`, and only fills days that have no showtimes yet. Options: `--now-showing=12`, `--coming-soon=8`, `--days=7`, `--no-showtimes`.

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Customer | `customer@layarku.test` | `password` |
| Staff | `staff@layarku.test` | `password` |

## Tests

```bash
php artisan test
```

Tests run against the MySQL database `layarku_test` (see `phpunit.xml`), never SQLite, so locking behaves like production. HTTP calls are blocked in tests (`Http::preventStrayRequests()`), so TMDB is always faked.

The `Concurrency` suite (`php artisan test --testsuite=Concurrency`) starts several real PHP processes that try to book seats at the same instant, each with its own database connection. It proves that exactly one wins when seats overlap. With the row lock removed, these tests fail with MySQL deadlocks, so they really exercise the lock.

## How double booking is prevented

1. `CreateBooking` runs in one transaction and first locks the showtime row (`SELECT ... FOR UPDATE`). Attempts for the same showtime run one at a time; other showtimes are not blocked.
2. Inside the lock it checks every requested seat and inserts the booking and its `booking_seats` rows, or rejects the whole request with `409 seat_taken` and the conflicting `seat_ids`.
3. A unique index on `booking_seats (showtime_id, seat_id)` is the second line of defence.
4. Every status change goes through `BookingStateMachine`, which enforces SPEC 4.3 and frees seats on `EXPIRED`/`CANCELLED`. Lock order everywhere is **showtime, then booking**, which avoids deadlocks between bookings, cancellations and the expiry job.

## API

All routes are under `/api`. Everything except register and login needs `Authorization: Bearer <token>`.

| Method and path | Purpose |
|---|---|
| `POST /auth/register` | Create a customer account, returns a token |
| `POST /auth/login` | Returns a token (`device_name` optional) |
| `POST /auth/logout` | Revokes the current token |
| `GET /me` · `PATCH /me` | Read or update name and phone |
| `GET /movies?status=now_showing\|coming_soon&per_page=20` | Paginated movie list |
| `GET /movies/{id}` | Movie details |
| `GET /movies/{id}/showtimes?date=YYYY-MM-DD&cinema_id=` | One day's showtimes (Jakarta date), grouped by cinema |
| `GET /showtimes/{id}/seats` | Seat map: each seat is `available`, `held` or `sold` |
| `POST /bookings` | Body `{ showtime_id, seat_ids[] }` (1-6 seats). Creates a `PENDING_PAYMENT` booking holding the seats for 10 minutes |
| `GET /bookings` | My bookings, newest first, paginated |
| `GET /bookings/{id}` | One of my bookings (`ticket_code` only once paid) |
| `POST /bookings/{id}/cancel` | Cancel an unpaid booking. Paid tickets are final |

Another customer's booking returns `404`, so the API never confirms that a booking id exists.

Every error has the same shape:

```json
{ "error": { "code": "validation_failed", "message": "The given data was invalid.", "details": { "fields": { "email": ["..."] } } } }
```

Codes so far:

| Code | Status | When |
|---|---|---|
| `validation_failed` | 422 | Invalid input (`details.fields`) |
| `seat_taken` | 409 | Some seats are already held or sold (`details.seat_ids`) |
| `booking_closed` | 422 | Less than 15 minutes before the start |
| `invalid_seats` | 422 | Seats from another studio (`details.seat_ids`) |
| `invalid_seat_count` | 422 | Not 1-6 seats |
| `booking_not_cancellable` | 422 | Booking is no longer unpaid |
| `invalid_status_transition` | 422 | A status change outside SPEC 4.3 |
| `invalid_credentials` | 401 | Wrong email or password |
| `unauthenticated` | 401 | Missing or invalid token |
| `forbidden` | 403 | Wrong role |
| `not_found` | 404 | Unknown or not yours |
| `too_many_requests` | 429 | Rate limit (auth, booking creation) |
| `server_error` | 500 | Unexpected error |

Timestamps are ISO 8601 in UTC (`2026-11-21T03:00:00Z`). Money is an integer in whole rupiah (`price_idr`).

## Code map

| Where | What |
|---|---|
| `app/Actions/` | Business logic, one class per action (`GetSeatMap`, `ListMovieShowtimes`, `ImportMoviesFromTmdb`, `CreateDemoShowtimes`, ...) |
| `app/Http/Controllers/Api/` | Thin controllers: validate, call an action, return a resource |
| `app/Http/Requests/` · `app/Http/Resources/` | Input validation · output shape |
| `app/Support/ApiError.php` | The single error format, wired in `bootstrap/app.php` |
| `app/Services/Tmdb/` | TMDB HTTP client |
| `config/cinema.php` | Business rules (time zone, 15-minute cut-off, 6 seats, 10-minute hold, demo schedule) |
