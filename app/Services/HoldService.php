<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SupplierMode;
use App\Models\Booking;
use App\Models\Partner;
use App\Repositories\BookingRepository;
use App\Repositories\CustomerTokenRepository;
use App\Repositories\FareRepository;
use App\Support\BearerToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HoldService
{
    public function __construct(
        private FareRepository $fares,
        private BookingRepository $bookings,
        private CustomerTokenRepository $customerTokens,
    ) {}

    /**
     * @return array{booking: Booking, customer_token: string}
     */
    public function createHold(
        Partner $partner,
        int $fareId,
        ?string $passengerName,
        SupplierMode $supplierMode,
    ): array {
        return DB::transaction(function () use ($partner, $fareId, $passengerName, $supplierMode) {
            $fare = $this->fares->decrementSeat($fareId);

            $booking = $this->bookings->create([
                'fare_id' => $fare->id,
                'partner_id' => $partner->id,
                'status' => BookingStatus::Hold,
                'base_fare_paisa' => $fare->base_fare_paisa,
                'tax_paisa' => $fare->tax_paisa,
                'total_paisa' => $fare->totalPaisa(),
                'refundable' => $fare->refundable,
                'fare_rules_snapshot' => $fare->fare_rules,
                'passenger_name' => $passengerName,
                'supplier_mode' => $supplierMode,
                'expires_at' => now()->addMinutes((int) config('booking.hold_ttl_minutes')),
            ]);

            $plaintextToken = Str::random(48);

            $this->customerTokens->create([
                'booking_id' => $booking->id,
                'token_hash' => BearerToken::hash($plaintextToken),
                'expires_at' => now()->addHours((int) config('booking.customer_token_ttl_hours')),
            ]);

            return ['booking' => $booking, 'customer_token' => $plaintextToken];
        });
    }
}
