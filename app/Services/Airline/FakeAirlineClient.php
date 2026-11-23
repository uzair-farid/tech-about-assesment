<?php

namespace App\Services\Airline;

use App\Enums\AirlineAttemptStatus;
use App\Enums\SupplierMode;
use App\Exceptions\Supplier\SupplierTimeoutException;
use App\Exceptions\Supplier\SupplierUnavailableException;
use App\Repositories\AirlineAttemptRepository;
use Illuminate\Support\Str;

/**
 * Stands in for the airline's ticketing API. Real network latency is not
 * actually slept through (that would make the test suite slow for no
 * benefit); "slow" mode instead reports a simulated latency that is
 * compared against our configured timeout, exactly like a real HTTP client
 * would abort a call that exceeds its timeout rather than waiting it out.
 */
class FakeAirlineClient
{
    public function __construct(private AirlineAttemptRepository $attempts) {}

    public function issue(string $idempotencyKey, int $bookingId, SupplierMode $mode): array
    {
        $attempt = $this->attempts->firstOrCreate($idempotencyKey, $bookingId);

        if ($attempt->status === AirlineAttemptStatus::Succeeded) {
            return ['pnr' => $attempt->pnr, 'duplicate' => true];
        }

        $attempt->attempt_count++;

        $timeoutMs = (int) config('services.airline.timeout_ms');
        $simulatedLatencyMs = $mode === SupplierMode::Slow
            ? (int) config('services.airline.slow_delay_ms')
            : 0;

        if ($simulatedLatencyMs > $timeoutMs) {
            $attempt->status = AirlineAttemptStatus::Failed;
            $attempt->last_error = "Supplier exceeded timeout ({$simulatedLatencyMs}ms > {$timeoutMs}ms).";
            $attempt->save();

            throw new SupplierTimeoutException($attempt->last_error);
        }

        if ($mode === SupplierMode::Flaky && $attempt->attempt_count === 1) {
            $attempt->status = AirlineAttemptStatus::Failed;
            $attempt->last_error = 'Simulated transient supplier failure.';
            $attempt->save();

            throw new SupplierUnavailableException($attempt->last_error);
        }

        $pnr = Str::upper(Str::random(6));

        $attempt->status = AirlineAttemptStatus::Succeeded;
        $attempt->pnr = $pnr;
        $attempt->last_error = null;
        $attempt->save();

        return ['pnr' => $pnr, 'duplicate' => false];
    }
}
