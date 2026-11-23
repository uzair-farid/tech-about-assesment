<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * All refund arithmetic in integer paisa. Two distinct refund shapes:
 * a full, no-fault refund (we failed the customer - expired hold took their
 * payment, the airline never issued a ticket, a cancel landed mid-payment),
 * and a customer-initiated cancellation, priced by the fare's own rules.
 */
class RefundService
{
    public function refundFull(Booking $booking): int
    {
        $amount = $booking->refundableRemainingPaisa();

        if ($amount > 0) {
            $booking->refunded_paisa += $amount;
        }

        $booking->status = BookingStatus::Refunded;
        $booking->cancelled_at ??= now();
        $booking->save();

        return $amount;
    }

    public function computeCancellationRefund(Booking $booking): int
    {
        if ($booking->refundable) {
            $fee = (int) config('booking.refundable_cancellation_fee_paisa');

            return max(0, $booking->total_paisa - $fee);
        }

        // Non-refundable fares forfeit the base fare; taxes are still returned.
        return $booking->tax_paisa;
    }

    public function applyCancellationRefund(Booking $booking): int
    {
        $refund = min($this->computeCancellationRefund($booking), $booking->refundableRemainingPaisa());

        if ($refund > 0) {
            $booking->refunded_paisa += $refund;
        }

        $booking->status = match (true) {
            $booking->refunded_paisa >= $booking->total_paisa => BookingStatus::Refunded,
            $refund > 0 => BookingStatus::PartiallyRefunded,
            default => BookingStatus::Cancelled,
        };
        $booking->cancelled_at = now();
        $booking->save();

        return $refund;
    }
}
