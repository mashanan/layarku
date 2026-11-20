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

Tests run against the MySQL database `layarku_test` (see `phpunit.xml`), never SQLite, so phase 2's locking tests behave like production. HTTP calls are blocked in tests (`Http::preventStrayRequests()`), so TMDB is always faked.

## API (phase 1)

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

Every error has the same shape:

```json
{ "error": { "code": "validation_failed", "message": "The given data was invalid.", "details": { "fields": { "email": ["..."] } } } }
```

Codes so far: `validation_failed` (422), `invalid_credentials` (401), `unauthenticated` (401), `forbidden` (403), `not_found` (404), `too_many_requests` (429), `server_error` (500).

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
