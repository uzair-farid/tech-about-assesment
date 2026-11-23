<?php

namespace App\Repositories;

use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;

class WebhookEventRepository
{
    /**
     * Inserts the event row, claiming the event_id. Returns null when
     * another delivery already claimed it first (duplicate webhook),
     * instead of throwing, so the caller can treat it as a no-op.
     */
    public function tryClaim(string $eventId, array $payload): ?WebhookEvent
    {
        try {
            return WebhookEvent::query()->create([
                'event_id' => $eventId,
                'payload' => $payload,
                'signature_valid' => true,
            ]);
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return null;
            }

            throw $e;
        }
    }
}
