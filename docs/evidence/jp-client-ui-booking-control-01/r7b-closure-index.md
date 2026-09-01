# JP-CLIENT-UI-BOOKING-CONTROL-01 — R7B closure index

## Authority

| Field | Value |
|---|---|
| Branch | `phase/jp-flight-perf-01` |
| R7_ORIGINAL_ENGINEERING_SHA | `44bb5290f705d4472479ccf1cf253fc38f104f3d` |
| R7_PRIOR_RUNTIME_SHA | `50ee1b0ca2dd2d6e5ec536aa77f9da446d339918` |
| R7B_ENGINEERING_FIX_SHA | `a975fbbc2f81854597d3cfebe1f00b7631ae752f` |
| R7B_FINAL_RUNTIME_SHA | `a975fbbc2f81854597d3cfebe1f00b7631ae752f` |
| R7B_EVIDENCE_SHA | `044b154d573c4bfa5583c27fbd1dc95b3a010d34` (+ index fix commit) |
| PUBLIC_BUILD_ID | `-iCjhEI48CuDPHExC0EvI` |
| DASHBOARD_BUILD_ID | `fbzOL_dHxc_Iq0ScPoglD` |
| REMOTE_HEAD (frozen) | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| Host | `https://jetpakistan.pk` |

## Defect fixed in R7B

Login Account-gate SSR called `laravelApiPath("/booking/commerce-gates")` → `/laravel/booking/commerce-gates`, failed open, and kept Sign up visible while `customer_registration_enabled=false`.

Fix: SSR uses `absoluteLaravelUrl("/booking/commerce-gates")`; client `fetchCommerceGates` uses same-origin `/booking/commerce-gates`.

Deployed under `/usr/local/sbin/jetpk-production-run` with PUBLIC_ONLY Next rebuild as `pkjetp`. Dashboard build unchanged.

## Proof inventory (`r7b-final/`)

| # | File | Result |
|---|---|---|
| 01 | `01-journey-sameday-dates.png` | Journey endpoint dates |
| 02 | `02-journey-overnight-dates.png` | Overnight dates |
| 03 | `03-return-branded-interleg-separator.png` | Brand separator + logo |
| 04 | `04-true-connection-layover-preserved.png` | True layover preserved |
| 05 | `05-square-pia-logo.png` | PIA square |
| 06 | `06-square-etihad-logo.png` / `06b-…` | Etihad square |
| 07 | `07-traveler-adult1-lead-header.png` (+360/768) | Lead header in card |
| 08 | `08-traveler-multi-pax-headers.png` (+ infant) | Multi-pax headers |
| 09–10 | Admin Guest ON/OFF UI | EXTERNAL — Admin QA password unavailable |
| 11 | `11-guest-off-registration-on.png` | Guest OFF + Register |
| 12 | `12-guest-off-registration-off.png` | Guest OFF + registration closed (post-fix) |
| 13–14 | Login/Register resume | EXTERNAL — Customer QA password / mailbox |
| 15 | `15-group-customer-on-*.png` | Group Book Now visible |
| 16 | `16-group-customer-off-detail.png` | Group detail while customer-group OFF |
| 17 | Group Agent while Customer OFF | EXTERNAL — Agent QA password unavailable |
| 18–20 | mobile traveler | `20-mobile-traveler-header.png` |

Note: `09-admin-guest-off-account-gate.png` is Guest-OFF Account gate (not Admin settings UI).

## Restored operational gates

```json
{"guest_booking_enabled":true,"card_payment_enabled":true,"customer_group_booking_enabled":true,"customer_registration_enabled":true}
```

FINAL_GUEST_BOOKING_SETTING=true

## Commercial / AI

SUPPLIER_MUTATION_CALLS=0 · AI_MODEL_CHANGED_IN_R7B=NO · AI_RUNTIME_CHANGED_IN_R7B=NO · AI_2B_TESTED=NO
