<?php

namespace App\Console\Commands;

use App\Services\HoldExpiryService;
use Illuminate\Console\Command;

class ExpireStaleHolds extends Command
{
    protected $signature = 'bookings:expire-stale';

    protected $description = 'Release seats for holds whose 20-minute window has passed without payment';

    public function handle(HoldExpiryService $holdExpiry): int
    {
        $count = $holdExpiry->sweepExpired();

        $this->info("Expired {$count} stale hold(s).");

        return self::SUCCESS;
    }
}
