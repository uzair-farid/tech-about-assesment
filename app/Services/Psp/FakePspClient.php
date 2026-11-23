<?php

namespace App\Services\Psp;

use App\Jobs\DeliverPspWebhookJob;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Stands in for the payment service provider. Acknowledges the charge
 * immediately (202-equivalent) and delivers the real outcome later through
 * an asynchronous, signed webhook - never synchronously - matching how
 * every real PSP (Stripe, Adyen, HBL/PayFast locally) actually behaves.
 */
class FakePspClient
{
    public function charge(Payment $payment, string $forcedOutcome = 'succeeded'): string
    {
        $pspReference = 'psp_'.Str::uuid()->toString();

        if (config('services.psp.simulate_webhook')) {
            DeliverPspWebhookJob::dispatch($payment->id, $pspReference, $forcedOutcome)
                ->delay(now()->addMilliseconds(random_int(0, 2000)));
        }

        return $pspReference;
    }
}
