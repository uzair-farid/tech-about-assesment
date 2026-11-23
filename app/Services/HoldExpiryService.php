<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Repositories\FareRepository;
use Illuminate\Support\Facades\DB;

/**
 * Hold expiry is enforced lazily (checked whenever a booking is touched) so
 * correctness never depends on a scheduler actually running. The scheduled
 * sweep in bookings:expire-stale just reclaims seats proactively instead of
 * waiting for the next request to notice.
 */
class HoldExpiryService
{
    public function __construct(
        private BookingRepository $bookings,
        private FareRepository $fares,
    ) {
    }

    public function expireIfDue(Booking $booking): Booking
    {
        if (! $booking->isHoldExpired()) {
            return $booking;
        }

        return DB::transaction(function () use ($booking) {
            $locked = $this->bookings->lockForUpdate($booking->id);

            if ($locked->isHoldExpired()) {
                $this->fares->incrementSeat($locked->fare_id);
                $locked->update(['status' => BookingStatus::Expired]);
            }

            return $locked;
        });
    }

    public function sweepExpired(): int
    {
        $count = 0;

        Booking::query()
            ->whereIn('status', [BookingStatus::Hold->value, BookingStatus::PaymentPending->value])
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $this->expireIfDue($booking);
                    $count++;
                }
            });

        return $count;
    }
}
