# Rematch safety

Matcher: `SabreSelectedOfferDeterministicMatcher`

## REMATCH_IDENTITY_FIELDS

- offer_id (exact)
- marketing carrier / airline_code
- flight_number(s)
- origin / destination (market-equivalent)
- departure datetime
- cabin
- segment chain (origin/dest/carrier/flight/dep per segment)
- brand_code / fare_basis / booking_class when branded context present

## R7D hardening

When branded context is present, itinerary-only match must also satisfy brand identity. Silent brand substitution rejected.

Price deltas do not block brand identity rematch; price change surfaces via fare-change acceptance.

| Gate | Value |
|---|---|
| WRONG_ITINERARY_REMATCH | 0 (unit: wrong flight number rejected) |
| SILENT_BRAND_SUBSTITUTION | 0 (unit: Smart→Freedom-only rejected) |

Tests: `BrandedFareCheckoutContextPersistenceTest` brand/flight rejection cases.
