<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository
{
    public function findByPartnerAndIdempotencyKey(int $partnerId, string $idempotencyKey): ?Payment
    {
        return Payment::query()
            ->where('partner_id', $partnerId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function create(array $attributes): Payment
    {
        return Payment::query()->create($attributes);
    }

    public function lockByPspReference(string $pspReference): ?Payment
    {
        return Payment::query()->where('psp_reference', $pspReference)->lockForUpdate()->first();
    }
}
