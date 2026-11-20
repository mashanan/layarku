<?php

namespace App\Console\Commands;

use App\Actions\Bookings\ExpireUnpaidBookings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire')]
#[Description('Expire unpaid bookings whose 10-minute seat hold has run out')]
class ExpireBookings extends Command
{
    public function handle(ExpireUnpaidBookings $expire): int
    {
        $count = $expire();
        $this->info("Expired {$count} booking(s).");

        return self::SUCCESS;
    }
}
