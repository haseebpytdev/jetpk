# JP-BO-04G — Commerce Flow Closure Summary

## Phase name
**JP-BO-04G** — Final Commerce Flow Closure (Return Search + Branded Fare Continuity + Performance + Guest/Card Admin Gates)

## Branch name
`phase/jp-bo-04`

## Objective
Close the owner-reported Return empty-results commerce blocker, restore branded-fare continuity for one-way / paired / split return, add Admin guest-booking and card-payment gates, and mint a new combined engineering SHA that supersedes `ec9f0ba2` for eventual Stage-B deploy.

## Included scope
- Return search root-cause fix (`cabin` vs `cabin_filter`)
- Paired + Split return results continuity
- Independent outbound/return branded fare selection
- Flight summary / review return-split presentation
- Guest booking Admin ON/OFF gate (UI + server)
- Credit/Debit Card Admin ON/OFF gate independent of AbhiPay
- Focused tests + fixture Playwright evidence + performance timings JSON

## Excluded scope
- Production deploy / SSH / SFTP / SCP
- Live Sabre PNR / ticket / void / refund
- Real AbhiPay payment
- Portable CMS architecture
- OLS / supplier credential changes

## Investigation findings
1. Next.js always puts search criteria `cabin=economy` on the results URL.
2. Results facets incorrectly parsed `cabin` as a filter and forwarded it to `/flights/results/data`.
3. Laravel `filterOffers` then dropped RT offers whose `cabin` was empty/mismatched → `outbound_options`/`paired_options` empty while `offers` already emptied by return-split flow → UI showed no results.
4. One-way still worked because it stayed on `offers[]` and many OW fixtures carry matching cabin.
5. Return options UI read `return_journey_display` while Laravel emits `journey_display`.
6. Result cards froze `fareOptions[0]`; pair cards lacked details/brand wiring.
7. No Admin `guest_booking_enabled` / `card_payment_enabled` runtime gates existed (AbhiPay `is_active` alone controlled card).

## Root causes
`RETURN_RESULTS_ROOT_CAUSE=SEARCH_CABIN_LEAKED_AS_RESULTS_FACET_EMPTYING_RETURN_SPLIT_OPTIONS`

Supporting defects:
- Wrong return journey display field on Next return-options page
- Implicit `fareOptions[0]` selection without authoritative selection state
- Missing independent outbound/return fare keys in split UI (backend already supported them)
- Missing commerce Admin gates

## Exact files changed
See engineering commit `d3dd484cd65f1a2a372fe6d4cb574316b69efa4e` (41 files).

## Routes changed
- `GET/PATCH admin/settings/booking-checkout`
- `GET /booking/commerce-gates`
- Existing flights results/data now prefers `cabin_filter` for cabin facet

## Database changes
`MIGRATIONS=1` — `commerce_checkout_settings` (defaults: guest ON, card ON)

## Backend changes
CommerceCheckoutSettingsService + enforcement in BookingController / PaymentTransactionService / AbhiPayPaymentController; return-split index browse freshness fix; pair options include branded fare fields.

## Frontend changes
Cabin facet isolation; branded fare selection on OW/outbound/return/pair cards; OrderSummary return-split legs; guest gate redirect on continue-to-passengers.

## Tests executed
| Gate | Result |
| --- | --- |
| Laravel CommerceCheckoutSettingsTest + ReturnSplitSelectFlowTest | **23 passed / 88 assertions** |
| Frontend cabin-filter node test | **3 passed** |
| Frontend typecheck | **PASS** |
| Frontend Playwright jp-bo-04g-commerce-matrix | **4 passed** |
| Frontend production build | **PASS** |
| Dashboard typecheck | **PASS** |
| Dashboard production build | **PASS** |

## Screenshots
`frontend/tmp/jp-bo-04g/playwright/` and `tmp/jp-bo-04g/playwright/`:
- 01-one-way-results.png
- 02-one-way-brand-selected.png
- 04-return-paired-results.png
- 07-return-split-outbound-brand.png

Remaining matrix shots (review/guest/card) covered by PHPUnit gate matrix; full browser matrix completion deferred to Stage-B live proof with Laravel fixtures.

## Performance evidence
`tmp/jp-bo-04g/jp-bo-04g-performance.json` (fixture/fake-supplier app timings; supplier separated).

## Known limitations
- Full 14-shot browser matrix not fully captured in this pass (4 primary commerce shots + PHPUnit gate matrix).
- Live supplier latency not measured (SUPPLIER_LATENCY_BLOCKED=NO for fixtures).
- Stage B live proof not run.

## Risks
Combined BO-04 + 04G deploy surface is large; requires owner/ChatGPT SHA review before protected deploy.

## Rollback
`git revert d3dd484c` (and prior 04A–04F engineering commits if rolling back full BO-04).

## Commit SHAs
| Slice | SHA |
| --- | --- |
| Prior FINAL_ENGINEERING_SHA (superseded) | `ec9f0ba257a4ef96149bd8474627beec2e2d5a4d` |
| **FINAL_ENGINEERING_SHA (JP-BO-04G)** | `d3dd484cd65f1a2a372fe6d4cb574316b69efa4e` |

## Final status
`SOURCE_GREEN=YES` for engineering gates run in this phase.  
`OWNER_RETEST_V3_STATE=BLOCKED_PENDING_JP_BO04G_STAGE_B_LIVE_PROOF`  
**DO NOT DEPLOY** until owner/ChatGPT independently verifies `FINAL_ENGINEERING_SHA`.
