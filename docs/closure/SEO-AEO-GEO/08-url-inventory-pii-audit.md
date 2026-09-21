# Visible URL inventory — PII / sensitive data audit

**Date:** 2026-09-21  
**Runtime baseline:** `5f78a5e1` (+ candidate short-URL cutover)

## Summary gates

| Gate | Status |
|---|---|
| PII_IN_URLS | **0** (no email/phone/passport/name in public paths/query) |
| SENSITIVE_DATA_IN_URLS | **0** for Class A SEO; transactional may carry opaque session ids |
| SUPPLIER_INTERNAL_IDS_IN_URLS | **0** on short path; legacy results may still carry `offer_id` / fare keys in checkout handoff (review separately) |
| LONG_SERIALIZED_STATE_URLS | **0** after short-ref default cutover for new searches |

## Inventory

| Surface | Visible URL shape | Index | Notes |
|---|---|---|---|
| Homepage / SEO pages | `/`, `/about-us`, `/support`, `/faq`, … | yes | Human short SEO |
| Flight search (default after cutover) | `/flights/s/{ref}` | no | Opaque; criteria query optional for chrome only |
| Flight search (legacy) | `/flights/results?…&search_id=` | no | Compatibility; noindex; not canonical |
| Traveler / passengers | `/booking/passengers?…` | no | Session/criteria — no PII fields in path |
| Review / payment / confirmation | `/booking/review`, `/booking/confirmation` | no | Session-backed |
| Customer booking | `/customer/bookings/{booking_reference}` | no | Human booking ref (entropy OK) |
| Agent booking | `/agent/bookings/{booking_reference}` | no | Same |
| Guest access | `/guest/bookings/{id}/access/{token}` | no | 64-char token — keep |
| Lookup | `/lookup-booking` | no | Form POST; no PII in URL |
| Group hub | `/groups` | yes | Discovery |
| Group search | `/groups/search?…` | no | Facets only |
| Group package | `/groups/{packageId}` | no | Inventory id — not supplier PNR |
| Group booking | `/groups/booking/{ref}/…` | no | Opaque booking ref |
| Share | `/l/{code}` | no | Planned; not live |
| Voucher | email / portal links | no | Prefer tokenized guest access |
| Email deep links | booking_reference / signed guest token | no | No passport/email in path |

## Residual risks (accepted / tracked)

1. Legacy `/flights/results?search_id=` remains for bookmarks — **noindex**, not advertised in sitemap.
2. Checkout may still pass `offer_id` / `fare_option_key` in query during Traveler handoff — not PII; separate offer-opaque pass if product requires.
3. `booking_reference` is intentionally human-readable and already high-entropy enough per §34 — do not replace unnecessarily.

## Cutover verification checklist

- [ ] New search lands on `/flights/s/{ref}`
- [ ] Refresh / back / forward keep short URL
- [ ] Expired ref → Search expired (no `search_id` in URL)
- [ ] Legacy long URL still loads
- [ ] Pair / Segmented / Traveler handoff still work
