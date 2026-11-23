# Travel.pk public pages — what has to be captured before a hold can exist

Twenty minutes on `travel.pk`'s public pages (homepage, search, and the
Terms of Service / Refund Policy / Privacy Policy footer pages), 2026-11-23.
The search-results and passenger-details steps are rendered client-side and
didn't return usable markup to a plain fetch, so this is necessarily partial
— flagged below wherever it applies.

## What the public pages show

- **Search form** (homepage): trip type, origin, destination, departure
  date, traveller count (defaults to 1 adult), cabin class, stops — no
  login required to search.
- **Pre-payment disclosure, stated but not observed in a rendered flow**:
  the homepage explicitly promises "Check baggage, changes and refunds
  before payment" and "Review the passenger names, itinerary, price and
  available payment method before confirming" — i.e. the site's own
  stated intent is to show fare rules and a final review step before
  money moves, which matches this service's hold → review → pay shape.
- **Terms of Service**: the customer, not Travel.pk, is responsible for
  passenger names, passport details and travel documents matching official
  ID. Price is explicitly not locked until "the supplier reconfirms the
  selected offer," and a payment receipt "is not by itself a ticket or
  supplier confirmation" — both map directly onto this service's price
  snapshot being provisional until the airline stub actually returns a
  PNR.
- **Refund Policy**: no universal cancellation fee table — refund amount
  and timeline both follow whatever rule is attached to the specific
  booking/payment rail. This is the real-world version of the
  refundable-vs-non-refundable, fare-rules-snapshot approach this service
  takes (store the rule *with* the booking at hold time, since the fare's
  own rules can change later).
- **Privacy Policy**: collects name, email, phone, passport info and
  payment details; shares passenger data with the airline/supplier to
  fulfil the booking. No mention found of CNIC, date of birth, or a
  minor-passenger consent flow.
- Not found on any reachable page: a stated hold/TTL duration, a
  fare-error clause, or a "reserve now, pay later" product (the only
  "hold" language present was an unrelated prepaid-wallet feature).

## What must be captured before a hold can legally exist

Reasoning from the above plus ordinary PK travel-agency practice:

1. **Full passenger name(s) matching ID**, and a live contact channel
   (email + phone) — the Terms place the accuracy burden on the customer,
   so the capture step itself needs to force the match rather than accept
   a free-text name later reconciled against a ticket.
2. **Travel document identity**: CNIC for domestic PK sectors, passport
   (plus expiry) for international ones — not shown explicitly on the
   pages I could reach, but implied by "passport information" in the
   privacy policy and standard airline/API requirements for PNR issuance.
3. **Explicit acknowledgement of the fare rules shown at hold time**
   (refundable/non-refundable, baggage, the price-recheck-before-payment
   warning) — not just a buried link, since the Refund Policy explicitly
   disclaims a single uniform policy and instead points back at
   whatever's attached to that specific fare.
4. **Consent to data sharing with the airline/supplier**, per the Privacy
   Policy — this is what legally lets the backend hand passenger data to
   the (here, stubbed) airline API at ticketing time.
5. **A price/availability re-check immediately before payment is taken**,
   per "prices can change until the supplier reconfirms" — this service's
   snapshot-at-hold-time plus hold-expiry design is the mechanical
   equivalent of that requirement; it's also exactly why a payment landing
   after the hold's window closed can't be allowed to silently keep the
   seat.

This service's hold endpoint currently accepts only `fare_id` and an
optional free-text `passenger_name` — it does not model CNIC/passport
capture or a fare-rules consent checkbox. That's a deliberate scope cut
(see README, "what we traded away"): the task is the state-machine and
money handling around the hold/payment/ticket flow, not a full passenger
data model, but a real pre-hold step would need items 1-4 above collected
and validated *before* the seat is decremented, not after.

## Three questions

1. **Is the 20-minute hold a product promise to the customer, or purely an
   inventory-protection mechanism for us?** If a customer-visible countdown
   is expected, the API needs to expose `expires_at` prominently (it
   already does) and the product probably wants a renewal/extension path,
   which isn't in scope here.
2. **For non-refundable fares, does "only taxes are refunded" match your
   actual airline contracts, or do some suppliers charge a cancellation
   fee against the tax portion too?** I priced it as the simplest common
   case; real fare rules vary by airline and this would need to come from
   the supplier's actual fare rules payload, not a flat policy.
3. **When ticketing fails after a successful payment (airline down, GDS
   error), do you want an automatic refund (what this service does), or a
   manual ops queue first** — since PSP refunds themselves aren't
   instant/free and a customer-visible instant refund promise may not
   match what the PSP integration can actually guarantee?
