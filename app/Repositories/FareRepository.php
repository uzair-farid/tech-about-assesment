<?php

namespace App\Repositories;

use App\Exceptions\NotFoundApiException;
use App\Exceptions\SeatUnavailableException;
use App\Models\Fare;

class FareRepository
{
    public function find(int $id): ?Fare
    {
        return Fare::query()->find($id);
    }

    /**
     * Atomically claims one seat. The WHERE guard makes this safe under
     * concurrent requests: only one of two racing updates can match a row
     * that still has seats left, regardless of how many processes run it
     * at the same instant.
     */
    public function decrementSeat(int $fareId): Fare
    {
        $fare = $this->find($fareId);

        if (! $fare) {
            throw new NotFoundApiException('Fare not found.');
        }

        $affected = Fare::query()
            ->where('id', $fareId)
            ->where('seats_left', '>', 0)
            ->decrement('seats_left');

        if ($affected === 0) {
            throw new SeatUnavailableException;
        }

        return Fare::query()->findOrFail($fareId);
    }

    public function incrementSeat(int $fareId): void
    {
        Fare::query()->where('id', $fareId)->increment('seats_left');
    }
}
