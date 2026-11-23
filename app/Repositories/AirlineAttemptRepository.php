<?php

namespace App\Repositories;

use App\Models\AirlineAttempt;

class AirlineAttemptRepository
{
    public function firstOrCreate(string $idempotencyKey, int $bookingId): AirlineAttempt
    {
        return AirlineAttempt::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            ['booking_id' => $bookingId],
        );
    }

    public function lockByIdempotencyKey(string $idempotencyKey): ?AirlineAttempt
    {
        return AirlineAttempt::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }
}
