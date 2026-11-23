<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPayloadException;
use App\Exceptions\Supplier\SupplierTimeoutException;
use App\Exceptions\Supplier\SupplierUnavailableException;
use App\Models\Payment;
use App\Repositories\BookingRepository;
use App\Repositories\FareRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\WebhookEventRepository;
use App\Services\Airline\AirlineTicketingService;
use Illuminate\Support\Facades\DB;

class WebhookService
{
    public function __construct(
        private WebhookEventRepository $events,
        private PaymentRepository $payments,
        private BookingRepository $bookings,
        private FareRepository $fares,
        private AirlineTicketingService $ticketing,
        private RefundService $refunds,
        private HoldExpiryService $holdExpiry,
    ) {}

    /**
     * Idempotent in two layers: the event_id claim stops a byte-for-byte
     * redelivery, and re-checking the payment's status after locking its
     * row stops two differently-IDed notifications for the same outcome
     * from being applied twice.
     */
    public function handlePspEvent(array $payload): array
    {
        $eventId = trim((string) ($payload['event_id'] ?? ''));

        if ($eventId === '') {
            throw new InvalidPayloadException('event_id is required.');
        }

        $pspReference = trim((string) ($payload['payment_reference'] ?? ''));

        if ($pspReference === '') {
            throw new InvalidPayloadException('payment_reference is required.');
        }

        return DB::transaction(function () use ($eventId, $pspReference, $payload) {
            $claimed = $this->events->tryClaim($eventId, $payload);

            if (! $claimed) {
                return ['status' => 'ignored_duplicate_event'];
            }

            $payment = $this->payments->lockByPspReference($pspReference);

            if (! $payment) {
                $claimed->update(['processed_at' => now()]);

                return ['status' => 'ignored_unknown_payment'];
            }

            if ($payment->status !== PaymentStatus::Initiated) {
                $claimed->update(['processed_at' => now(), 'payment_id' => $payment->id]);

                return ['status' => 'already_processed', 'payment_status' => $payment->status->value];
            }

            $outcome = (string) ($payload['status'] ?? '');

            $result = $outcome === 'succeeded'
                ? $this->handleSucceeded($payment)
                : $this->handleFailed($payment, (string) ($payload['failure_reason'] ?? 'declined'));

            $claimed->update(['processed_at' => now(), 'payment_id' => $payment->id]);

            return $result;
        });
    }

    private function handleFailed(Payment $payment, string $reason): array
    {
        $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => $reason]);

        $booking = $this->holdExpiry->expireIfDue($this->bookings->lockForUpdate($payment->booking_id));

        if ($booking->status === BookingStatus::PaymentPending) {
            $this->fares->incrementSeat($booking->fare_id);

            $cancelling = $booking->cancel_requested_at !== null;
            $booking->update([
                'status' => $cancelling ? BookingStatus::Cancelled : BookingStatus::PaymentFailed,
                'cancelled_at' => $cancelling ? now() : null,
            ]);
        }

        return ['status' => 'payment_failed', 'booking_status' => $booking->status->value];
    }

    private function handleSucceeded(Payment $payment): array
    {
        $payment->update(['status' => PaymentStatus::Succeeded]);

        $booking = $this->holdExpiry->expireIfDue($this->bookings->lockForUpdate($payment->booking_id));

        if ($booking->status === BookingStatus::Expired) {
            $refunded = $this->refunds->refundFull($booking);

            return ['status' => 'refunded_hold_expired', 'booking_status' => $booking->status->value, 'refunded_paisa' => $refunded];
        }

        if ($booking->cancel_requested_at !== null) {
            $this->fares->incrementSeat($booking->fare_id);
            $refunded = $this->refunds->refundFull($booking);

            return ['status' => 'refunded_customer_cancelled', 'booking_status' => $booking->status->value, 'refunded_paisa' => $refunded];
        }

        $booking->update(['status' => BookingStatus::Paid]);

        try {
            $pnr = $this->ticketing->issueTicket($booking->fresh());
            $booking->update(['status' => BookingStatus::Ticketed, 'pnr' => $pnr]);

            return ['status' => 'ticketed', 'booking_status' => $booking->status->value, 'pnr' => $pnr];
        } catch (SupplierTimeoutException|SupplierUnavailableException $e) {
            $booking->update(['status' => BookingStatus::TicketFailed]);
            $refunded = $this->refunds->refundFull($booking);

            return [
                'status' => 'ticketing_failed_refunded',
                'booking_status' => $booking->status->value,
                'refunded_paisa' => $refunded,
                'supplier_error' => $e->getMessage(),
            ];
        }
    }
}
