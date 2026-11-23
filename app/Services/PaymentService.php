<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingStateConflictException;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\Psp\FakePspClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private PaymentRepository $payments,
        private HoldExpiryService $holdExpiry,
        private FakePspClient $psp,
    ) {
    }

    /**
     * @return array{status: int, body: array}
     */
    public function startPayment(Partner $partner, Booking $booking, string $idempotencyKey, array $requestPayload): array
    {
        $fingerprint = hash('sha256', json_encode($requestPayload, JSON_THROW_ON_ERROR));

        $existing = $this->payments->findByPartnerAndIdempotencyKey($partner->id, $idempotencyKey);

        if ($existing) {
            return $this->replay($existing, $fingerprint);
        }

        $booking = $this->holdExpiry->expireIfDue($booking);

        if ($booking->status === BookingStatus::Expired) {
            throw new HoldExpiredException();
        }

        if ($booking->status !== BookingStatus::Hold) {
            throw new BookingStateConflictException(
                "Booking is {$booking->status->value}, not awaiting payment."
            );
        }

        try {
            $payment = DB::transaction(fn () => $this->payments->create([
                'booking_id' => $booking->id,
                'partner_id' => $partner->id,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'amount_paisa' => $booking->total_paisa,
                'status' => PaymentStatus::Initiated,
            ]));
        } catch (QueryException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }

            // Lost the race to another request with the same key; replay its outcome.
            $existing = $this->payments->findByPartnerAndIdempotencyKey($partner->id, $idempotencyKey);

            return $this->replay($existing, $fingerprint);
        }

        $pspReference = $this->psp->charge($payment);

        $booking->update(['status' => BookingStatus::PaymentPending]);

        $body = [
            'payment_id' => $payment->id,
            'booking_reference' => $booking->reference,
            'status' => 'pending',
            'amount_paisa' => $payment->amount_paisa,
        ];

        $payment->update([
            'psp_reference' => $pspReference,
            'response_status' => 202,
            'response_body' => $body,
        ]);

        return ['status' => 202, 'body' => $body];
    }

    private function replay(Payment $payment, string $fingerprint): array
    {
        if ($payment->request_fingerprint !== $fingerprint) {
            throw new IdempotencyKeyConflictException();
        }

        if ($payment->response_status === null) {
            throw new IdempotencyKeyInProgressException();
        }

        return ['status' => $payment->response_status, 'body' => $payment->response_body];
    }
}
