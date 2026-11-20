# CLAUDE.md

Guidance for Claude Code when working in this repository.

## About the project
**Layarku** is a cinema ticket booking app. Customers browse movies, pick a showtime, choose seats on a seat map, pay through Midtrans (sandbox) and get a QR ticket. Cinema staff scan the QR at the studio door.

The cinema brand in the demo is **fictional** ("Layar Kota Cinema"). Never use the names or logos of real cinema chains.

This is a monorepo with two parts:

- `backend/`: Laravel REST API (the source of truth)
- `android/`: Kotlin and Jetpack Compose client

This is a portfolio project, so code clarity matters as much as features. The owner wants to understand every architectural decision.

Product spec, data model, API list and phase plan: see [SPEC.md](SPEC.md) (in Indonesian).

## Stack

### Backend (`backend/`)
- PHP and the latest stable Laravel, Sanctum for token auth
- MySQL or PostgreSQL for development and for any test that touches row locks. SQLite only for quick tests that don't depend on locking
- Pest (or PHPUnit) for tests
- Laravel scheduler and queue for expiring unpaid bookings and sending reminders
- TMDB API (imported once by an artisan command) for movie data, Midtrans Snap (sandbox) for payments, Firebase Cloud Messaging (HTTP v1) for push

### Android (`android/`)
- Kotlin, Gradle Kotlin DSL with a version catalog (`gradle/libs.versions.toml`)
- Jetpack Compose with Material 3, Navigation Compose (type-safe routes via `@Serializable`)
- MVVM with `ui` / `domain` / `data` layers
- Hilt for DI, KSP for annotation processing (no kapt)
- Retrofit, OkHttp and kotlinx.serialization for networking, Coil for posters
- Room for caching "My tickets" offline, DataStore for the auth token and settings
- Firebase Cloud Messaging for push, Custom Tabs for the Midtrans payment page
- CameraX and ML Kit barcode scanning for the staff scanner, a QR generator library for tickets
- Tests: JUnit 4, MockK, kotlinx-coroutines-test, Turbine

Package: `com.hanan.layarku`. The Android emulator reaches the local backend at `http://10.0.2.2:8000`.

## Android package structure
```
com.hanan.layarku
├─ data/
│  ├─ remote/       Retrofit API, DTOs, token interceptor, error mapper
│  ├─ local/        Room, DataStore
│  ├─ repository/   implementations + DTO/entity <-> domain mappers
│  └─ di/           Hilt modules
├─ domain/
│  ├─ model/        plain Kotlin data classes
│  ├─ repository/   interfaces
│  └─ usecase/      one action per class
└─ ui/
   ├─ theme/  navigation/  components/  util/
   ├─ auth/  movies/  moviedetail/  showtimes/  seatpicker/  checkout/  tickets/  profile/
   └─ staff/          (ticket scanner and today's showtimes)
```
Dependency rule: `ui` → `domain` ← `data`. `domain` must not import Android or `data` classes.

## Backend conventions
- Controllers stay thin. Business rules live in action or service classes (`CreateBooking`, `GetSeatMap`, `ExpireUnpaidBookings`, `CreateMidtransTransaction`, `ValidateTicket`).
- Validate with Form Requests, shape responses with API Resources.
- Errors use one format: `{ "error": { "code": "seat_taken", "message": "...", "details": { ... } } }` with the right HTTP status (`409` seat taken, `422` rule violation, `403` wrong role).
- **No double-booked seats.** Creating a booking runs in one DB transaction that locks the showtime row (`lockForUpdate`), checks every requested seat, then inserts the booking and its `booking_seats`. A unique index on `(showtime_id, seat_id)` is the second line of defence. Any change here needs a concurrency test, including partially overlapping seat sets.
- Booking status changes go through one place that enforces the allowed transitions (see SPEC.md section 4.3).
- Paid tickets are final: no cancellation and no refund. Only `PENDING_PAYMENT` bookings can be cancelled.
- `ticket_code` is long and random, never sequential. Only the server decides whether a ticket is valid, and a used ticket must be rejected.
- The Midtrans webhook must verify `signature_key`, match `order_id` and amount, and be idempotent. A payment arriving after the booking expired is flagged `REFUND_NEEDED` and must not revive the booking.
- Times are stored in UTC. The cinema time zone is `Asia/Jakarta`. Money is an integer in whole rupiah.
- Every endpoint checks ownership, and `/staff/*` rejects other roles.
- Rate-limit auth and booking creation, because the demo API is public.

## Android code conventions
- **Language**: user-facing text lives in string resources, never hardcoded. English is the default (`res/values/strings.xml`) and Bahasa Indonesia the translation (`res/values-in/strings.xml`). **Every new string goes into both**. Code, identifiers, comments and commit messages in English.
- **Money** is `Long` in whole rupiah. Never `Double` or `Float`. Format with a shared `formatRupiah()` helper.
- **Dates and times** use `java.time`. Never call `Instant.now()` or `LocalDate.now()` directly in logic: inject `java.time.Clock` so tests can fix "now". The API sends ISO 8601 in UTC and the UI shows times in `Asia/Jakarta`.
- **Domain is pure Kotlin** so it can be unit tested on the JVM. Seat selection rules (maximum 6 seats, total price) live in the domain.
- **Seat map**: draw from `grid_x` / `grid_y`, keep touch targets large enough, add content descriptions per seat for screen readers, and show a legend. Refresh the map periodically while the screen is open and immediately after a `409 seat_taken`.
- **Tickets** must open offline: store the booking and `ticket_code` in Room and generate the QR on the device.
- **Screens** follow this pattern:
  - `XxxScreen(uiState, onEvent...)`: stateless and previewable
  - `XxxRoute()`: gets the ViewModel with `hiltViewModel()`, collects state with `collectAsStateWithLifecycle()`
  - `XxxViewModel`: exposes `StateFlow<XxxUiState>`, with one-off events (navigation, snackbar) through a separate flow
- **Network results** are wrapped so that no-internet, `409` and `422` each map to a clear UI message. Never show raw exception text to the user.
- **Use cases**: one responsibility each, exposed as `operator fun invoke(...)`.
- **Dispatchers**: inject with a Hilt qualifier (`@IoDispatcher`) instead of hardcoding them.
- **Dependencies** are always added through the version catalog, never as inline strings.
- **Tests**: every non-UI piece of logic (use cases, ViewModels, error mapping, time formatting) gets unit tests in `src/test`. DAO tests go in `src/androidTest` with an in-memory database.
- Prefer small, readable functions and comments that explain *why*, not *what*.

## Movie data and branding
- Movie data comes from TMDB. Before using it, check TMDB's current terms for attribution and data storage, and show the required attribution in the app (About screen) and in the README.
- Keep the cinema name, logo and any promotional text fictional.

## Secrets (read this twice)
This repository is public. **Never commit a secret**, and never put one anywhere git remembers.
- Never commit: Midtrans server key, TMDB key, FCM service account JSON, `APP_KEY`, database passwords, keystores, `keystore.properties`, `local.properties`, or any `.env`.
- Keep a `.env.example` with empty values. Real values go in `.env` (git-ignored) or GitHub Actions secrets.
- `google-services.json` for the Android client may be committed, but nothing from the server side may.
- Before every commit, check `git status` and `git diff --staged` for keys. If a secret was ever committed, rotate it, because deleting the file does not remove it from history.

## Commands

### Backend (from `backend/`)
| Task | Command |
|---|---|
| Install | `composer install` |
| Reset and seed demo data | `php artisan migrate:fresh --seed` |
| Import movies from TMDB | `php artisan movies:import` |
| Reset demo data and shift showtimes | `php artisan demo:reset` |
| Run locally | `php artisan serve` |
| Tests | `php artisan test` |
| Process queue | `php artisan queue:work` |
| Run scheduler locally | `php artisan schedule:work` |

### Android (Windows, from `android/`)
| Task | Command |
|---|---|
| Build debug APK | `.\gradlew.bat assembleDebug` |
| Unit tests | `.\gradlew.bat testDebugUnitTest` |
| Lint | `.\gradlew.bat lintDebug` |
| Instrumented tests (device or emulator) | `.\gradlew.bat connectedDebugAndroidTest` |

`JAVA_HOME` may not be set globally. In a terminal session, use Android Studio's bundled JDK first:
`$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"`

## Working agreement
- Work on **one phase (or step) per request**, following the phase table in SPEC.md. Present a short plan first and wait for approval before changing code.
- After finishing a phase, run the relevant tests (`php artisan test` for backend work, `assembleDebug` and `testDebugUnitTest` for Android work), then summarize what changed and the test results. Report failures honestly.
- End every phase with a commit (`feat: phase N ...`) and `git push` to `origin main`, so progress is visible on GitHub.
- Briefly explain every architectural decision. The owner wants to understand the code.
- Don't add features or dependencies outside the approved plan.
- If a request conflicts with SPEC.md, point out the conflict and ask instead of silently choosing.
