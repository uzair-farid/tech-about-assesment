<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Simulates the PSP's asynchronous callback for local/demo use. Runs
 * in-process rather than over real HTTP, since there is no real PSP to
 * authenticate against here - the webhook route itself still verifies a
 * genuine signed request the same way for anyone calling it from outside.
 */
class DeliverPspWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $paymentId,
        public string $pspReference,
        public string $outcome,
    ) {}

    public function handle(WebhookService $webhooks): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if (! $payment) {
            return;
        }

        $webhooks->handlePspEvent([
            'event_id' => 'evt_'.Str::uuid()->toString(),
            'payment_reference' => $this->pspReference,
            'status' => $this->outcome,
            'amount_paisa' => $payment->amount_paisa,
            'failure_reason' => $this->outcome === 'failed' ? 'card_declined' : null,
        ]);
    }
}
