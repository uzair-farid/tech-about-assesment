<?php

namespace Tests\Feature;

use App\Enums\AirlineAttemptStatus;
use App\Enums\BookingStatus;
use App\Enums\SupplierMode;
use App\Models\AirlineAttempt;
use App\Services\Airline\FakeAirlineClient;
use Tests\Feature\Concerns\SignsPspWebhooks;

class SupplierFailureTest extends FeatureTestCase
{
    use SignsPspWebhooks;

    public function test_a_slow_supplier_times_out_and_the_booking_is_refunded(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 2]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner, [
            'supplier_mode' => SupplierMode::Slow,
        ]);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-slow'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();

        $response = $this->postSignedWebhook([
            'event_id' => 'evt-slow',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ticketing_failed_refunded');
        $booking->refresh();
        $this->assertSame(BookingStatus::Refunded, $booking->status);
        $this->assertSame($booking->total_paisa, $booking->refunded_paisa);

        $attempt = AirlineAttempt::query()->where('idempotency_key', "ticket-{$booking->reference}")->sole();
        $this->assertSame(AirlineAttemptStatus::Failed, $attempt->status);
        $this->assertSame((int) config('services.airline.max_attempts'), $attempt->attempt_count);
    }

    public function test_a_flaky_supplier_succeeds_on_retry_under_the_same_idempotency_key(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 2]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner, [
            'supplier_mode' => SupplierMode::Flaky,
        ]);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-flaky'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();

        $response = $this->postSignedWebhook([
            'event_id' => 'evt-flaky',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ticketed');
        $booking->refresh();
        $this->assertSame(BookingStatus::Ticketed, $booking->status);
        $this->assertNotEmpty($booking->pnr);

        $attempt = AirlineAttempt::query()->where('idempotency_key', "ticket-{$booking->reference}")->sole();
        $this->assertSame(2, $attempt->attempt_count);
    }

    public function test_the_airline_stub_is_duplicate_safe_under_the_same_idempotency_key(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $client = app(FakeAirlineClient::class);

        $first = $client->issue('ticket-dup-key', bookingId: $booking->id, mode: SupplierMode::Normal);
        $second = $client->issue('ticket-dup-key', bookingId: $booking->id, mode: SupplierMode::Normal);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['pnr'], $second['pnr']);
        $this->assertSame(1, AirlineAttempt::query()->count());
    }
}
