<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

/**
 * Flips pending (unpaid) bookings whose hold window has lapsed to "expired",
 * releasing their seats. Seats are freed immediately by the holdingSeats scope;
 * this command keeps the stored status honest for listings and reporting.
 */
class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire pending bookings whose unpaid hold window has elapsed';

    public function handle(): int
    {
        $count = Booking::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} unpaid booking hold(s).");

        return self::SUCCESS;
    }
}
