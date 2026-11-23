<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use Tests\Feature\Concerns\SignsPspWebhooks;

class CancelTest extends FeatureTestCase
{
    use SignsPspWebhooks;

    public function test_cancelling_a_hold_releases_the_seat_with_no_refund(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 4]);
        [$booking, $customerToken] = $this->createHoldWithCustomerToken($fare, $partner);

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->reference}/cancel",
            [],
            $this->authHeaders($customerToken),
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'cancelled');
        $response->assertJsonPath('refunded_paisa', 0);
        $this->assertSame(4, $fare->fresh()->seats_left);
    }

    public function test_cancel_during_a_pending_payment_is_deferred_until_the_outcome_is_known(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking, $customerToken] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-defer'],
        )->assertStatus(202);

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->reference}/cancel",
            [],
            $this->authHeaders($customerToken),
        );

        $response->assertStatus(202);
        $response->assertJsonPath('status', 'cancellation_pending');
        $this->assertNotNull($booking->fresh()->cancel_requested_at);
        $this->assertSame(BookingStatus::PaymentPending, $booking->fresh()->status);

        $payment = $booking->payments()->sole();
        $this->postSignedWebhook([
            'event_id' => 'evt-cancel-then-pay',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ])->assertJsonPath('status', 'refunded_customer_cancelled');

        $booking->refresh();
        $this->assertSame(BookingStatus::Refunded, $booking->status);
        $this->assertSame($booking->total_paisa, $booking->refunded_paisa);
    }

    public function test_cancelling_a_refundable_ticketed_booking_deducts_the_cancellation_fee(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare(['refundable' => true]);
        [$booking, $customerToken] = $this->createHoldWithCustomerToken($fare, $partner, [
            'status' => BookingStatus::Ticketed,
            'pnr' => 'ABC123',
        ]);

        $fee = (int) config('booking.refundable_cancellation_fee_paisa');
        $expectedRefund = $booking->total_paisa - $fee;

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->reference}/cancel",
            [],
            $this->authHeaders($customerToken),
        );

        $response->assertOk();
        $response->assertJsonPath('refunded_paisa', $expectedRefund);
        $this->assertSame(BookingStatus::PartiallyRefunded, $booking->fresh()->status);
    }

    public function test_cancelling_a_non_refundable_ticketed_booking_only_returns_the_tax(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare(['refundable' => false]);
        [$booking, $customerToken] = $this->createHoldWithCustomerToken($fare, $partner, [
            'refundable' => false,
            'status' => BookingStatus::Ticketed,
            'pnr' => 'XYZ789',
        ]);

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->reference}/cancel",
            [],
            $this->authHeaders($customerToken),
        );

        $response->assertOk();
        $response->assertJsonPath('refunded_paisa', $booking->tax_paisa);
        $this->assertSame(BookingStatus::PartiallyRefunded, $booking->fresh()->status);
    }

    public function test_cannot_cancel_an_already_cancelled_booking(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking, $customerToken] = $this->createHoldWithCustomerToken($fare, $partner, [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->reference}/cancel",
            [],
            $this->authHeaders($customerToken),
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'booking_state_conflict');
    }
}
