# Layarku

> 🚧 **Work in progress.** Portfolio project, currently at phase 1 (backend foundation). See [backend/README.md](backend/README.md) to run the API.

**Layarku** is a cinema ticket booking app. Customers browse movies, pick a showtime, choose seats on a live seat map, pay through Midtrans (sandbox) and receive a QR ticket that opens offline. Cinema staff scan the QR at the studio door, and only the server decides whether a ticket is valid.

The cinema brand in the demo, **"Layar Kota Cinema"**, is entirely fictional. It is not affiliated with any real cinema chain.

## Highlights (planned)

- **No double-booked seats.** Booking runs in one database transaction with a row lock on the showtime, backed by a unique index on `(showtime_id, seat_id)` and covered by concurrency tests.
- **Payments.** Midtrans Snap in sandbox mode, with a signature-verified, idempotent webhook.
- **Server push.** Firebase Cloud Messaging for payment confirmation and a reminder 2 hours before the movie.
- **Offline tickets.** Stored in Room, with the QR generated on the device.
- **Two roles.** Customer app and a staff ticket scanner (CameraX + ML Kit).

## Repository structure

```
.
├─ backend/   Laravel REST API (Sanctum, MySQL/PostgreSQL, Pest)
├─ android/   Kotlin + Jetpack Compose client (MVVM, Hilt, Retrofit, Room)
├─ SPEC.md    Product spec, data model, API and phase plan (Indonesian)
└─ CLAUDE.md  Engineering conventions
```

## Stack

| Part | Technology |
|---|---|
| Backend | PHP, Laravel, Sanctum, MySQL/PostgreSQL, Pest, scheduler + queue |
| Android | Kotlin, Jetpack Compose, Material 3, Hilt, Retrofit, kotlinx.serialization, Room, DataStore, Coil |
| Integrations | Midtrans Snap (sandbox), Firebase Cloud Messaging, TMDB |

## Roadmap

| Phase | Scope | Status |
|---|---|---|
| 0 | Monorepo scaffold | ✅ |
| 1 | Backend: Laravel, migrations, seeders, TMDB import, auth, movies/showtimes/seats | ✅ |
| 2 | Backend: bookings, row locking, expiry, cancellation, concurrency tests | ⏳ |
| 3 | Android: project setup, theme, navigation, login and register | ⏳ |
| 4 | Android: movies, showtimes, seat map, order summary | ⏳ |
| 5 | Payments and QR tickets | ⏳ |
| 6 | Push notifications and reminders | ⏳ |
| 7 | Staff scanner | ⏳ |
| 8 | Deploy, CI, release APK, docs, demo video | ⏳ |

## Product decisions

- Paid tickets are final: **no cancellation and no refund**, like most cinemas. Only unpaid bookings can be cancelled.
- Unpaid bookings hold seats for 10 minutes, then expire automatically.

## Secrets

This repository is public. No secrets are committed. Copy `backend/.env.example` to `backend/.env` and fill in your own keys.

## Non-commercial demo

Layarku is a **non-commercial portfolio project**. "Layar Kota Cinema" does not exist, no tickets are sold, and payments run only in Midtrans **sandbox** mode, so no real money ever moves.

## Attribution

This application uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB.

Movie titles, overviews and posters come from [The Movie Database (TMDB)](https://www.themoviedb.org/). Posters are loaded directly from TMDB's image CDN. TMDB's terms allow caching for at most six months, so `php artisan movies:import` refreshes the stored data and is meant to run on a schedule on the demo server.
