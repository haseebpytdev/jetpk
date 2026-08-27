# JP-GRP-UI-01 — Checkout auth continuation status

## Branch

`phase/jp-grp-ui-01`

## Engineering SHAs

| Role | SHA |
|---|---|
| Prior UI pack | `2296631f68c710003a666e1c118a180448b7c9c0` |
| Hub proxy | `6b086e1517a6a9061294e93e2f1181d948c59714` |
| **Public-id binding fix (current runtime)** | `996c7e6862387819582d84f30be850507356652f` |

## What unblocked Book Now

Live detail JSON for `ALH-*` 404ed under `route:cache` because `Route::bind` in `web.php` never registered. Model `resolveRouteBinding` restores public-id package lookup. Deploy verify: `PACKAGE_PUBLIC_ID_HTTP=200`.

## Acceptance (continuation slice)

| Gate | Status |
|---|---|
| GROUP_BOOK_NOW_HANDLER | PASS (live detail + modal) |
| GROUP_LOGIN_MODAL | PASS |
| LOGIN_MODAL_ERROR_STATE | PASS |
| GROUP_CHECKOUT_AUTH_REQUIRED | YES (401/302) |
| ANONYMOUS_CHECKOUT_BLOCKED | PASS |
| POST_LOGIN_RETURN_TO_GROUP_CHECKOUT | NOT_RUN (needs owner test login) |
| LIVE_CHECKOUT_FORM | NOT_RUN (needs owner test login) |
| GROUP_BOOKING_ENABLED | FALSE |
| GROUP_RESERVATION_ENABLED | FALSE |
| LIVE_REAL_PAYMENT | NOT_RUN_SAFETY |
| LIVE_REAL_GROUP_BOOKING | NOT_RUN_SAFETY |
| ALHAIDER_TOKEN_GENERATION_CALLS | 0 |

## Evidence

`docs/evidence/jp-grp-ui-01/20260827T150400Z/`

## Local fake-supplier E2E

`tests/Feature/GroupTicketing/LocalFakeSupplierCheckoutE2ETest.php` — PASS (37 assertions)

Anonymous 401 → JSON login resume → 2 pax → price/seat guards → local hold → manual payment → admin verify → confirmation + IDOR. `ALHAIDER_BOOKING_ENABLED=false` throughout (`supplier_reservation_id` remains null).

## Live disposable Customer UAT (2026-08-27T153500Z)

TEMP_CUSTOMER_CREATED=YES  
TEMP_CUSTOMER_CLEANUP_REQUIRED=YES  
USER_ID=13  
EMAIL=`qa.jp.grp.ui01.20260827203255@example.com`  
ACCOUNT_TYPE=customer  

Evidence: `docs/evidence/jp-grp-ui-01/20260827T153500Z/`

Live: anonymous Book Now → modal → login → resume passengers → summary + read-only price/seat. **No booking/payment submit.** Gates OFF.

## Hard stop

STOP before real Al-Haider booking/payment and before enabling booking/reservation gates.

Cleanup required: delete disposable user id 13 after owner/ChatGPT review.
