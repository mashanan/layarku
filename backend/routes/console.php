<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. Locally: `php artisan schedule:work`. On a server: one cron
| entry running `php artisan schedule:run` every minute.
*/

// Release seats of unpaid bookings once their 10-minute hold runs out.
// withoutOverlapping: if one run is slow, the next minute's run is skipped
// instead of two runs fighting over the same rows.
Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();
