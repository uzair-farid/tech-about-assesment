<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerToken;
use App\Models\Fare;
use App\Models\Partner;
use App\Support\BearerToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Partner, 1: string}
     */
    protected function partnerWithKey(array $scopes = ['holds.write', 'payments.write', 'bookings.read', 'bookings.write', 'fares.read']): array
    {
        return Partner::factory()->createWithKey(['scopes' => $scopes]);
    }

    protected function authHeaders(string $plaintextKey): array
    {
        return ['Authorization' => "Bearer {$plaintextKey}"];
    }

    protected function createFare(array $attributes = []): Fare
    {
        return Fare::factory()->create($attributes);
    }

    /**
     * Creates a hold directly against the booking table (bypassing the HTTP
     * layer) and returns the booking plus a usable plaintext customer token,
     * for tests that only care about what happens after the hold exists.
     *
     * @return array{0: Booking, 1: string}
     */
    protected function createHoldWithCustomerToken(Fare $fare, Partner $partner, array $bookingAttributes = []): array
    {
        $fare->decrement('seats_left');

        $booking = Booking::factory()->create([
            'fare_id' => $fare->id,
            'partner_id' => $partner->id,
            'base_fare_paisa' => $fare->base_fare_paisa,
            'tax_paisa' => $fare->tax_paisa,
            'total_paisa' => $fare->totalPaisa(),
            'refundable' => $fare->refundable,
            'fare_rules_snapshot' => $fare->fare_rules,
            ...$bookingAttributes,
        ]);

        $plaintext = Str::random(48);

        CustomerToken::factory()->create([
            'booking_id' => $booking->id,
            'token_hash' => BearerToken::hash($plaintext),
        ]);

        return [$booking, $plaintext];
    }
}
