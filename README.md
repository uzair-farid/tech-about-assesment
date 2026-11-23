# Travel.pk fare-hold service

A small HTTP service that takes a fare from hold → payment → issued
ticket, built around the ninety seconds where money and seat inventory
can most easily fall out of sync. Synthetic exercise: no real payment or
airline APIs, no credentials, public pages only. See `RESEARCH.md` for
the travel.pk public-page notes and `STATES.md` for the full state
table.

## Stack

- **PHP 8.2 / Laravel 12**, SQLite (file-based) for zero-setup local
  storage — "local DB" per the brief, and portable to any reviewer's
  machine without a MySQL/Postgres server.
- Plain Eloquent + a thin repository layer (no query builder magic, no
  ORM-agnostic abstraction nobody asked for).
- No external services: the PSP and the airline are both in-process fakes
  (`App\Services\Psp\FakePspClient`, `App\Services\Airline\FakeAirlineClient`).

## Install, run, test

```bash
composer install
cp .env.example .env        # already defaults to sqlite
php artisan key:generate
touch database/database.sqlite   # if it doesn't already exist
php artisan migrate --seed       # creates the schema + 2 demo partner keys + 6 fares
php artisan serve                # http://127.0.0.1:8000

# in another terminal, to see the PSP's async webhook actually arrive:
php artisan queue:work
```

`composer test` runs the whole suite in one command (41 tests, SQLite
in-memory, no network, ~1s). `php artisan test` works too.

The seeder prints two bearer keys to the console — copy them from there,
or re-run `php artisan db:seed --class=Database\\Seeders\\PartnerSeeder`.
They're fixed, documented, local-only fixtures, not real secrets:

```
Travel.pk Backend (full access): Bearer demo_full_11a9f3c7b2e84d6fa0c5e9d1b3a7f2c4
Travel.pk Reporting (read-only): Bearer demo_readonly_7f2a9c1e4b6d803f5a2c7e9b1d4f6a80
```

## Data model and money

Six fare tables down to one real decision: **money is always an integer
count of paisa** (`App\Support\Money`), never a float. Supplier prices
arrive as decimal rupee strings (`"84250.50"`) and are parsed by regex
into whole rupees + a zero-padded 2-digit paisa remainder — no
floating-point multiplication anywhere near money.

Schema (`database/migrations/`): `partners` (bearer key hash + scopes),
`fares` (route, carrier, price breakdown, `seats_left`, `fare_rules`
json), `bookings` (the hold *is* the booking — one row carries it from
`hold` through to `ticketed`/`refunded`, with a price snapshot and
fare-rules snapshot taken at hold time so later fare changes can't
retroactively change what a customer already locked in), `payments`
(doubles as the idempotency ledger), `webhook_events` (event-id dedup),
`airline_attempts` (per-booking ticketing ledger, keyed by a fixed
idempotency key), `customer_tokens`.

Six seeded fares across `LHE-KHI`, `KHI-DXB`, `ISB-JED`
(`database/seeders/FareSeeder.php`): two non-refundable (AirBlue
LHE-KHI, FlyDubai KHI-DXB), and the Emirates KHI-DXB fare has exactly one
seat left, for the last-seat race.

## Auth

Two unrelated credential types, both opaque bearer tokens, both hashed
(SHA-256) at rest — only the plaintext ever shown once, at creation.

- **Partner keys**, scoped (`holds.write`, `payments.write`,
  `bookings.read`, `bookings.write`, `fares.read`). Missing scope → `403`.
  Invalid/inactive key → `401`.
- **Customer tokens**, opaque, 24h, minted one per booking at hold time
  and scoped to exactly that booking. A valid token against a *different*
  booking's reference returns `404`, not `403` — it should look
  indistinguishable from that booking not existing, not reveal that it
  exists but isn't yours.

Both share the `Authorization: Bearer <token>` header; `GET/POST
bookings/{reference}` middleware tries a partner key first, then falls
back to a customer token scoped to that specific booking.

## API

All responses: `{"data": {...}}` on resource reads, or a flat body for
actions; errors are always `{"error": {"code": "...", "message": "..."}}`.
Money fields are integer paisa.

### `POST /api/v1/holds` — partner, scope `holds.write`

```json
{ "fare_id": 3, "passenger_name": "Ali Raza", "supplier_mode": "normal" }
```

`supplier_mode` (`normal` | `slow` | `flaky`, default `normal`) is a
**test/demo hook**, not something a real partner API would expose — it's
how a caller forces the fake airline's behaviour for this one booking, so
the failure paths can be driven deterministically instead of relying on a
real supplier actually misbehaving.

`201` — booking (`status: "hold"`, `expires_at` 20 minutes out) +
`customer_token` (shown once). `409 seat_unavailable` if the fare has
no seats left. `422` if `fare_id` doesn't exist.

### `POST /api/v1/holds/{reference}/payments` — partner, scope `payments.write`

Requires `Idempotency-Key`. No body is required — the charge amount is
always the booking's own price snapshot, never client-supplied, so a
caller can't alter what gets charged by editing the request.

`202` `{"payment_id", "booking_reference", "status": "pending", "amount_paisa"}`.
Same key + same body → the exact same `202` replayed, no new charge. Same
key + different body → `409 idempotency_key_conflict`. Same key, another
request for it still in flight → `409 idempotency_key_in_progress`.
Hold already expired → `409 hold_expired` (and the seat is released as a
side effect of noticing). Booking not currently `hold` for any other
reason → `409 booking_state_conflict`. Missing header → `400
idempotency_key_required`.

### `POST /api/webhooks/psp` — HMAC-SHA256 signed, no bearer auth

Headers `X-Psp-Timestamp` (unix seconds) and `X-Psp-Signature` =
`hash_hmac('sha256', "{timestamp}.{raw body}", shared_secret)`, tolerance
`PSP_WEBHOOK_TOLERANCE_SECONDS` (default 300s). Invalid or stale → `401
invalid_webhook_signature`.

```json
{ "event_id": "evt_...", "payment_reference": "psp_...", "status": "succeeded", "failure_reason": null }
```

Always `200` (the PSP only cares that delivery succeeded); the business
outcome is in the body: `ticketed`, `payment_failed`,
`refunded_hold_expired`, `refunded_customer_cancelled`,
`ticketing_failed_refunded`, `already_processed`,
`ignored_duplicate_event`, `ignored_unknown_payment`. See `STATES.md`.

Signing a webhook by hand, against a running `php artisan serve`:

```bash
ts=$(date +%s)
body='{"event_id":"evt-1","payment_reference":"psp_...","status":"succeeded"}'
sig=$(php -r 'echo hash_hmac("sha256",$argv[1].".".$argv[2],$argv[3]);' "$ts" "$body" "local-dev-psp-shared-secret")
curl -X POST http://127.0.0.1:8000/api/webhooks/psp \
  -H "Content-Type: application/json" -H "X-Psp-Timestamp: $ts" -H "X-Psp-Signature: $sig" -d "$body"
```

### `GET /api/v1/bookings/{reference}` — partner (`bookings.read`) or customer token

Reading a booking past its `expires_at` lazily expires it first (releases
the seat) — correctness never depends on the scheduler having run.

### `POST /api/v1/bookings/{reference}/cancel` — partner (`bookings.write`) or customer token

- `hold` / `payment_failed` → `200`, seat released, no refund (nothing
  was charged).
- `payment_pending` → `202 cancellation_pending`; the payment's real
  outcome isn't known yet, so nothing is released or refunded until the
  webhook resolves it (see `STATES.md`).
- `paid` / `ticketed`, refundable fare → `200`, refund = total minus a
  flat cancellation fee (`REFUNDABLE_CANCELLATION_FEE_PAISA`, default
  PKR 1,500).
- `paid` / `ticketed`, non-refundable fare → `200`, refund = tax only,
  base fare forfeited.
- Anything already settled (`expired`, `cancelled`, `refunded`,
  `partially_refunded`) → `409 booking_state_conflict`.

### `GET /api/v1/fares` — partner, scope `fares.read`

Lists the seeded fares, for picking a `fare_id` to test against.

## Worked example

Real output from a running instance (`php artisan serve`), the seeded
one-seat Emirates fare (`fare_id=3`), against a freshly-seeded database —
included so this can be reviewed without having to run anything.

**1. Hold the last seat, then try again**

```
$ curl -X POST http://127.0.0.1:8000/api/v1/holds \
    -H "Authorization: Bearer demo_full_11a9f3c7b2e84d6fa0c5e9d1b3a7f2c4" \
    -H "Content-Type: application/json" \
    -d '{"fare_id":3,"passenger_name":"Sana Tariq"}'
```
```json
{
  "data": {
    "reference": "TPK-E5ZCWD",
    "status": "hold",
    "fare": {"origin": "KHI", "destination": "DXB", "carrier_code": "EK", "flight_number": "EK607", "departs_at": "2026-12-11T02:15:00+00:00"},
    "pricing": {"currency": "PKR", "base_fare_paisa": 8425050, "tax_paisa": 987525, "total_paisa": 9412575, "refunded_paisa": 0},
    "refundable": true, "passenger_name": "Sana Tariq", "pnr": null,
    "expires_at": "2026-11-23T13:09:03+00:00", "cancel_requested_at": null, "cancelled_at": null,
    "created_at": "2026-11-23T12:49:03+00:00"
  },
  "customer_token": "erowCE3HjHGEQ1rDtabaM2MUV9zaflSkGbXi1EvjvrAJDyyf"
}
```
A second hold on the same fare, immediately after:
```json
{"error": {"code": "seat_unavailable", "message": "No seats are left on this fare."}}
```
`HTTP 409`

**2. Start a payment, then replay it, then try reusing the key with a different body**

```
$ curl -X POST http://127.0.0.1:8000/api/v1/holds/TPK-E5ZCWD/payments \
    -H "Authorization: Bearer demo_full_11a9f3c7b2e84d6fa0c5e9d1b3a7f2c4" \
    -H "Idempotency-Key: pay-key-001" -d '{}'
```
```json
{"payment_id": 1, "booking_reference": "TPK-E5ZCWD", "status": "pending", "amount_paisa": 9412575}
```
`HTTP 202` — the identical request replayed with the same key returns this
exact same body, `HTTP 202` again, with no second payment row created.
The same key with a different body instead returns:
```json
{"error": {"code": "idempotency_key_conflict", "message": "This Idempotency-Key was already used with a different request payload."}}
```
`HTTP 409`

**3. The PSP's signed webhook arrives, ticketing happens synchronously**

```
$ ts=$(date +%s)
$ body='{"event_id":"evt-demo-1","payment_reference":"psp_...","status":"succeeded"}'
$ sig=$(php -r 'echo hash_hmac("sha256",$argv[1].".".$argv[2],$argv[3]);' "$ts" "$body" "local-dev-psp-shared-secret")
$ curl -X POST http://127.0.0.1:8000/api/webhooks/psp \
    -H "Content-Type: application/json" -H "X-Psp-Timestamp: $ts" -H "X-Psp-Signature: $sig" -d "$body"
```
```json
{"status": "ticketed", "booking_status": "ticketed", "pnr": "6FZ3PN"}
```
`HTTP 200`

**4. Cancel the ticketed booking (refundable fare, total minus the flat fee)**

```
$ curl -X POST http://127.0.0.1:8000/api/v1/bookings/TPK-E5ZCWD/cancel \
    -H "Authorization: Bearer erowCE3HjHGEQ1rDtabaM2MUV9zaflSkGbXi1EvjvrAJDyyf"
```
```json
{"status": "partially_refunded", "refunded_paisa": 9262575}
```
`HTTP 200` — `9412575 - 150000` (the flat refundable-cancellation fee) `=
9262575`, exactly.

## Screenshots

The same flow above, watched change row-by-row in phpMyAdmin
(`docs/screenshots/`). This run pointed the identical codebase at a local
MySQL database instead of SQLite — no code changes, `DB_CONNECTION=mysql`
in `.env` only — purely so there was an admin GUI to screenshot; SQLite
stays the committed default (see "What we traded away").

| | |
|---|---|
| [`01-fares-before.png`](docs/screenshots/01-fares-before.png) | Baseline: 6 seeded fares, the Emirates KHI-DXB row (`id=3`) at `seats_left=1`. |
| [`02-fares-after-hold.png`](docs/screenshots/02-fares-after-hold.png) | After `POST /holds`: `seats_left` for that row drops to `0`. |
| [`03-bookings-hold.png`](docs/screenshots/03-bookings-hold.png) | The new `bookings` row: `status=hold`, price snapshot taken. |
| [`04-bookings-payment-pending.png`](docs/screenshots/04-bookings-payment-pending.png) | After `POST .../payments`: `status` flips to `payment_pending`. |
| [`05-payments-initiated.png`](docs/screenshots/05-payments-initiated.png) | The `payments` row created by that call — the idempotency ledger (`idempotency_key`, `request_fingerprint`), `status=initiated`. |
| [`06-payments-succeeded.png`](docs/screenshots/06-payments-succeeded.png) | After the signed PSP webhook: `status=succeeded`. |
| [`07-bookings-ticketed.png`](docs/screenshots/07-bookings-ticketed.png) | Same webhook, same transaction: `bookings.status=ticketed`. |
| [`08-bookings-refunded.png`](docs/screenshots/08-bookings-refunded.png) | After `POST .../cancel`: `status=partially_refunded`. |
| [`09-fares-after-cancel.png`](docs/screenshots/09-fares-after-cancel.png) | `fares.seats_left` for `id=3` back to `1` — the seat was released. |

## Concurrency

The last-seat race is settled by one atomic statement —
`UPDATE fares SET seats_left = seats_left - 1 WHERE id = ? AND seats_left
> 0` — not a read-then-write check. Whichever request's `UPDATE` matches
a row wins; the other updates zero rows and gets `409`. This is correct
under real concurrent load on any SQL engine, not just under the
sequential test that exercises it (see "Tests" below for why it's tested
sequentially rather than with real parallel threads).

Idempotency is enforced the same way: the first `INSERT` into `payments`
under a given `(partner_id, idempotency_key)` wins; a concurrent duplicate
insert hits the unique constraint, and the loser re-reads the winner's
row to decide replay vs. conflict vs. in-progress.

Webhook double-delivery is handled the same way twice over: an `event_id`
claim (unique constraint) stops a byte-for-byte redelivery, and a
`lockForUpdate` + status re-check on the `payments` row stops two
*differently*-IDed deliveries for the same logical outcome from both
applying.

## Hold expiry

Enforced lazily first: any read or write that touches a booking past its
`expires_at` expires it on the spot (releases the seat, sets
`status=expired`) before doing anything else — correctness never depends
on a scheduler running. `php artisan bookings:expire-stale` (registered
in `routes/console.php` as an every-minute scheduled task) just reclaims
seats for bookings nobody has touched since they went stale, which
otherwise would stay inventory-locked until someone happens to read them.
Covers both `hold` and `payment_pending` bookings, since starting a
payment doesn't stop the clock (see "payment landing after hold expiry"
in `STATES.md`).

## Tests

`composer test` / `php artisan test` — 41 tests, 125 assertions, SQLite
in-memory, `QUEUE_CONNECTION=sync`, `PSP_SIMULATE_WEBHOOK=false` (tests
drive the webhook endpoint directly with a hand-signed payload instead of
waiting on the queued job, so they can assert exact timing and outcomes).

| Failure path from the brief | Status |
|---|---|
| Two holds racing for the last seat | **Done** — see caveat below |
| A replayed Idempotency-Key | Done |
| That key reused with a different body | Done |
| Supplier timeout then retry | Done |
| Payment landing after hold expiry | Done |
| A cancel during a pending payment | Done (both eventual-success and eventual-failure outcomes) |
| Flaky supplier, duplicate-safe retry | Done |
| Webhook replay / redelivery (event-id dedup) | Done |
| Two differently-id'd webhook deliveries for one payment | Done |
| Signature / timestamp verification | Done |
| Auth: scopes, cross-booking 404, expired/invalid tokens | Done |
| Money parsing from supplier strings | Done (unit) |
| **Partial** — two *simultaneous* payment-start requests under a brand-new key (the true network race, not the already-in-flight case) | The unique-constraint race path exists and is exercised by directly seeding an in-flight `payments` row rather than firing two real parallel HTTP requests from PHPUnit (which runs single-threaded) |
| **Partial** — the last-seat race is two *sequential* calls, not two OS threads | The atomic `UPDATE ... WHERE seats_left > 0` is what actually provides correctness under concurrency; sequential calls prove the guard rejects the second claim, which is the same code path real concurrent requests hit. Verified manually with two simultaneous `curl` requests against `php artisan serve` during development; not automated |
| **Not done** — rate limiting / abuse protection | Not implemented |
| **Not done** — load/performance testing at volume | Discussed qualitatively below, not benchmarked |
| **Not done** — voiding the PNR with the airline on cancel | Out of scope; cancel only does refund arithmetic + seat release |

A test asserting what it doesn't check costs a lot — so where a test says
"refunded", it asserts the exact `refunded_paisa` figure and the
resulting `status`, not just a `200`.

## What we traded away

- **SQLite over Postgres/MySQL**: zero setup for whoever's reviewing
  this, at the cost of SQLite's single-writer model (see below). The
  atomic-decrement and row-locking logic is written in plain Eloquent/
  query-builder calls that work unchanged on MySQL — swapping
  `DB_CONNECTION` is the only change needed to run it there.
- **Stubs run in-process, not as separate servers.** "Slow" supplier mode
  compares a configured simulated latency against the timeout rather than
  actually sleeping 6 seconds — a real HTTP client aborts at its timeout
  rather than waiting out the slow response anyway, and sleeping for real
  would make the test suite slow for no benefit.
- **The PSP's async webhook is delivered by an in-process queued job
  calling the same webhook-handling code the real HTTP route uses**,
  rather than a real self-loopback HTTP call — avoids flaky tests that
  depend on a real server listening on a real port while the queue
  worker fires. The HTTP route itself still does full HMAC verification
  independent of this, so `curl`-ing it from the outside works exactly as
  documented above.
- **Ticketing runs synchronously inside the webhook request**, not on its
  own queue. Simplest correct option for this scope; the real cost is
  below.
- **No "void the PNR" call to the airline on cancellation** — out of
  scope; refund arithmetic + seat release only.
- **Flat, global cancellation fee** rather than a fully supplier-driven
  fare-rules fee schedule — `fare_rules` is stored and returned per
  booking, but the actual fee number is one config value, for consistency
  across the fixture data rather than six different ad hoc numbers.
- **No partner-level booking ownership** — any partner key with the right
  scope can act on any booking; only the customer token is
  booking-scoped. Matches a single travel.pk backend calling its own
  internal service, not a true multi-tenant marketplace.

## What breaks first at 50 bookings a minute on one VPS

At 50/min (under 1 request/second, bursty at most), raw throughput isn't
actually the problem — a handful of writes per second is nothing for
either SQLite or a real RDBMS. Two things would hurt before the database
does:

1. **`php artisan serve` is single-threaded.** It's Symfony's dev server,
   not meant to run anything concurrently without
   `PHP_CLI_SERVER_WORKERS` set — one slow request (a webhook that
   happens to hit the "slow" supplier path and burns through 3 retries)
   stalls every other request behind it, including unrelated holds. This
   is the first real limit, and it's a deployment problem (php-fpm /
   Octane / nginx), not a design problem.
2. **Ticketing runs synchronously inside the webhook handler.** A real
   airline integration misbehaving ties up that request (and, given (1),
   the whole server) for the duration of every retry. The fix is moving
   `AirlineTicketingService::issueTicket` onto its own queued job keyed
   by the same idempotency key, so the webhook can `ack` immediately and
   a slow supplier call only blocks *that* booking's own ticketing job,
   not the next unrelated webhook.

Once those are fixed and volume climbs well past 50/min, **SQLite's
single-writer lock** becomes the next ceiling — every write transaction
(hold, payment, webhook, cancel) currently takes an exclusive lock for
its duration, and in SQLite's default rollback-journal mode (not WAL)
that blocks readers too, not just other writers. The fix at that point is
Postgres/MySQL with row-level locking, which this codebase already uses
(`lockForUpdate()`) in a way that's portable to either without changes.

## What I'd improve with more time

Beyond the scale fixes above:

- **Real passenger data capture at hold time** — CNIC/passport, date of
  birth, and an explicit fare-rules acknowledgement, per the `RESEARCH.md`
  findings. Right now a hold only takes a free-text `passenger_name`;
  that's the single biggest gap between this and something travel.pk
  could actually put in front of a customer.
- **Queue the airline ticketing call** off the webhook request (see
  above) — also makes it retryable/observable independently of the
  webhook's own delivery semantics, instead of both living or dying
  together in one request.
- **Per-fare cancellation terms** instead of one global fee — `fare_rules`
  is already stored and snapshotted per booking; the refund calculation
  should read its fee from there instead of a single config value, once
  real supplier fare-rules payloads are available to populate it.
- **Rate limiting** on the public endpoints (`holds`, `payments`) — 
  nothing stops one partner key from hammering `POST /holds` today.
- **An OpenAPI spec** generated from the routes/form-requests, so the API
  section of this README can't drift from the actual code.
- **Structured, correlatable logging** (one request/webhook id threaded
  through hold → payment → ticketing) — right now a reviewer has to
  follow a booking by its reference through the DB tables directly;
  production would want this in logs/traces instead.
- **A real self-HTTP loop for the PSP webhook demo**, as an opt-in mode
  alongside the current in-process delivery — closer to how a real PSP
  integration is smoke-tested, at the cost of needing a live server
  during that specific test.

## Environment

See `.env.example`. Domain-specific knobs:
`HOLD_TTL_MINUTES` (20), `CUSTOMER_TOKEN_TTL_HOURS` (24),
`PSP_WEBHOOK_SECRET`, `PSP_WEBHOOK_TOLERANCE_SECONDS` (300),
`AIRLINE_TIMEOUT_MS` (3000), `AIRLINE_SLOW_DELAY_MS` (6000),
`AIRLINE_MAX_ATTEMPTS` (3), `REFUNDABLE_CANCELLATION_FEE_PAISA` (150000 =
PKR 1,500).
