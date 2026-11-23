<?php

namespace App\Services\Airline;

use App\Exceptions\Supplier\SupplierTimeoutException;
use App\Exceptions\Supplier\SupplierUnavailableException;
use App\Models\Booking;

class AirlineTicketingService
{
    public function __construct(private FakeAirlineClient $client) {}

    /**
     * Retries under the same idempotency key so a flaky supplier that
     * actually ticketed on a lost-response attempt never gets double-booked;
     * the stub recognises the key and hands back the original PNR.
     *
     * @throws SupplierTimeoutException|SupplierUnavailableException when every attempt fails
     */
    public function issueTicket(Booking $booking): string
    {
        $idempotencyKey = "ticket-{$booking->reference}";
        $maxAttempts = max(1, (int) config('services.airline.max_attempts'));

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $this->client->issue($idempotencyKey, $booking->id, $booking->supplier_mode);

                return $result['pnr'];
            } catch (SupplierTimeoutException|SupplierUnavailableException $e) {
                $lastException = $e;
            }
        }

        throw $lastException;
    }
}
