<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Support\BookingReference;
use Illuminate\Database\QueryException;

class BookingRepository
{
    public function findByReference(string $reference): ?Booking
    {
        return Booking::query()->where('reference', $reference)->first();
    }

    public function lockForUpdate(int $id): Booking
    {
        return Booking::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function create(array $attributes): Booking
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Booking::query()->create([
                    ...$attributes,
                    'reference' => BookingReference::generate(),
                ]);
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not generate a unique booking reference.');
    }
}
