<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingStateConflictException;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Repositories\FareRepository;
use Illuminate\Support\Facades\DB;

class CancelService
{
    public function __construct(
        private BookingRepository $bookings,
        private FareRepository $fares,
        private HoldExpiryService $holdExpiry,
        private RefundService $refunds,
    ) {
    }

    /**
     * @return array{status: int, body: array}
     */
    public function cancel(Booking $booking): array
    {
        $booking = $this->holdExpiry->expireIfDue($booking);

        return DB::transaction(function () use ($booking) {
            $booking = $this->bookings->lockForUpdate($booking->id);

            return match ($booking->status) {
                BookingStatus::Hold, BookingStatus::PaymentFailed => $this->cancelWithoutCharge($booking),
                BookingStatus::PaymentPending => $this->deferCancellation($booking),
                BookingStatus::Paid, BookingStatus::Ticketed => $this->cancelWithRefund($booking),
                default => throw new BookingStateConflictException(
                    "Booking is already {$booking->status->value} and cannot be cancelled."
                ),
            };
        });
    }

    private function cancelWithoutCharge(Booking $booking): array
    {
        $this->fares->incrementSeat($booking->fare_id);
        $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

        return ['status' => 200, 'body' => [
            'status' => 'cancelled',
            'refunded_paisa' => 0,
        ]];
    }

    /**
     * Payment is already in flight with the PSP; its outcome is unknown
     * until the webhook arrives. We record the request and let the webhook
     * handler finalise the booking once it knows whether money moved.
     */
    private function deferCancellation(Booking $booking): array
    {
        if ($booking->cancel_requested_at === null) {
            $booking->update(['cancel_requested_at' => now()]);
        }

        return ['status' => 202, 'body' => [
            'status' => 'cancellation_pending',
            'message' => 'Payment is in flight; cancellation will be finalised once the payment outcome is known.',
        ]];
    }

    private function cancelWithRefund(Booking $booking): array
    {
        $this->fares->incrementSeat($booking->fare_id);
        $refunded = $this->refunds->applyCancellationRefund($booking);

        return ['status' => 200, 'body' => [
            'status' => $booking->status->value,
            'refunded_paisa' => $refunded,
        ]];
    }
}
