# Booking state machine

A booking is created the moment a hold is placed and carries the same
`reference` (`TPK-XXXXXX`) through its whole life — there is no separate
"hold" entity. `status` is the single source of truth; `expires_at`,
`cancel_requested_at`, `refunded_paisa` and the linked `payments` /
`airline_attempts` rows record *why* a transition happened.

States: `hold` · `expired` · `payment_pending` · `payment_failed` · `paid`
(transient) · `ticketed` · `ticket_failed` (transient) · `cancelled` ·
`refunded` · `partially_refunded`.

`paid` and `ticket_failed` are written to the row but are immediately
followed, in the same request/webhook transaction, by ticketing or a
refund — they're real persisted states, just short-lived.

| From | Event | To | Side effects |
|---|---|---|---|
| *(none)* | `POST /holds`, seat available | `hold` | `fares.seats_left -= 1` via `UPDATE ... WHERE seats_left > 0` (atomic); booking row created with a price snapshot; `expires_at = now + 20m`; customer token issued |
| *(none)* | `POST /holds`, **two requests race for the last seat** | one → `hold`, the other → *(no row)* | The update above matches for only one of the two concurrent requests, regardless of timing; the loser gets `409 seat_unavailable` with no row created and no seat change |
| `hold` | `POST .../payments` before `expires_at` | `payment_pending` | `payments` row created (`status=initiated`); PSP stub `charge()` called, returns a reference immediately; booking moves to `payment_pending` |
| `hold` | `POST .../payments`, **Idempotency-Key replayed with the same body** | unchanged | No new payment row; the first request's cached response (status + body) is replayed verbatim |
| `hold` | `POST .../payments`, **same Idempotency-Key, different body** | unchanged | `409 idempotency_key_conflict`; no side effects |
| `hold` | `POST .../payments`, **same Idempotency-Key, another request still mid-flight** | unchanged | `409 idempotency_key_in_progress`; no side effects (the in-flight request's own completion is what the client should retry against) |
| `hold` | any touch (`GET`, payment start, cancel) after `expires_at` has passed | `expired` | `fares.seats_left += 1` (released exactly once — guarded by the status check inside the same locked transaction); no refund (nothing was ever charged) |
| `hold` | `POST .../cancel` | `cancelled` | `fares.seats_left += 1`; `refunded_paisa` stays `0` |
| `payment_pending` | PSP webhook `succeeded`, hold window still open | `paid` → `ticketed` | `payments.status=succeeded`; airline `issueTicket()` called synchronously; on success, `pnr` stored and status becomes `ticketed` |
| `payment_pending` | PSP webhook `succeeded`, **airline ticketing exhausts retries** (slow/flaky supplier) | `paid` → `ticket_failed` → `refunded` | `airline_attempts` row records every attempt and the final error; booking refunded in full (`refunded_paisa = total_paisa`) since no ticket could be issued |
| `payment_pending` | PSP webhook `succeeded`, **but `expires_at` has already passed** ("payment landing after hold expiry") | `expired` → `refunded` | Seat released (once — skipped if a sweep/lazy check already released it); `refunded_paisa = total_paisa`; no ticketing is attempted, since the seat may already be sold to someone else |
| `payment_pending` | PSP webhook `failed` | `payment_failed` | `payments.status=failed`, `failure_reason` recorded; `fares.seats_left += 1` |
| `payment_pending` | PSP webhook `failed`, **but a cancel was requested while the payment was in flight** | `cancelled` | Same seat release as above; no refund needed (nothing was ever captured) |
| `payment_pending` | PSP webhook `succeeded`, **but a cancel was requested while the payment was in flight** | `refunded` | Seat released; full refund issued, since money did move but the customer no longer wants the seat |
| `payment_pending` | `POST .../cancel` (payment already in flight, outcome unknown) | unchanged (`cancel_requested_at` set) | `202 cancellation_pending`; no seat/refund change yet — finalised by whichever of the two rows above fires when the webhook lands. Calling cancel again just re-returns the same pending state |
| `payment_pending` | webhook **redelivered with the same `event_id`** | unchanged | No-op; the event-id claim fails on a unique-constraint race, so the second delivery short-circuits to `ignored_duplicate_event` before touching the payment |
| `payment_pending` | a **second, differently-`event_id`'d** webhook arrives for an already-resolved payment | unchanged | No-op; the payment row is locked and re-checked (`status !== initiated`) before any business logic runs, so this returns `already_processed` regardless of the new event's own id |
| `payment_pending` | scheduled sweep (`bookings:expire-stale`) or a lazy read, before any webhook has arrived | `expired` | Seat released; the eventual webhook (whenever it lands) then takes the "payment landing after hold expiry" row above |
| `paid`, `ticketed` | `POST .../cancel`, refundable fare | `partially_refunded` (or `refunded` if the fee is 0) | `fares.seats_left += 1`; `refunded_paisa += total_paisa - cancellation_fee_paisa` |
| `paid`, `ticketed` | `POST .../cancel`, non-refundable fare | `partially_refunded` (or `cancelled` if tax is 0) | `fares.seats_left += 1`; `refunded_paisa += tax_paisa` (base fare forfeited) |
| `expired`, `cancelled`, `refunded`, `partially_refunded` | `POST .../cancel` | unchanged | `409 booking_state_conflict`; no side effects |

## Notes on specific failure paths

- **Last-seat race**: enforced by one atomic `UPDATE fares SET seats_left =
  seats_left - 1 WHERE id = ? AND seats_left > 0`, not by a separate
  check-then-act or an app-level lock. Whichever request's statement
  commits first wins; the second one simply updates zero rows.
- **Replayed / conflicting / in-flight Idempotency-Key**: the `payments`
  table *is* the idempotency ledger (`UNIQUE(partner_id, idempotency_key)`),
  keyed off a hash of the request body so a replay is detected by content,
  not just by header.
- **Supplier timeout then retry**: `AirlineTicketingService` retries the
  same booking under one fixed idempotency key
  (`ticket-{booking reference}`); the stub recognises the key on retry and
  never issues two PNRs for one booking.
- **Payment landing after hold expiry**: the 20-minute window is checked
  against `expires_at` regardless of whether the booking is `hold` or
  `payment_pending` — starting a payment does not stop the clock, because
  until the PSP confirms, we don't actually know money is coming.
- **Cancel during a pending payment**: never raced against the webhook.
  Cancelling only sets a flag; the webhook handler (which already holds a
  row lock on the booking) is the single place that decides the final
  state once the payment's real outcome is known.
