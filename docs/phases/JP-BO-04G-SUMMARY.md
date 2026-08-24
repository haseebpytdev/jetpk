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
8. **Independent GitHub review (post-`d3dd484c`):** `ReturnOptionsPage.handleSelect` resolved return fare from `selectedFareByCombo[comboId]` before `combo.fare_option_key`. `BrandedFareCarousel.onBook` called `setSelectedFareByCombo` then immediately `handleSelect`, so a direct Book on a non-default return brand could submit the previous/default key (visible Comfort, submitted Basic).

## Root causes
`RETURN_RESULTS_ROOT_CAUSE=SEARCH_CABIN_LEAKED_AS_RESULTS_FACET_EMPTYING_RETURN_SPLIT_OPTIONS`

Supporting defects:
- Wrong return journey display field on Next return-options page
- Implicit `fareOptions[0]` selection without authoritative selection state
- Missing independent outbound/return fare keys in split UI (backend already supported them)
- Missing commerce Admin gates
- Split return Book race: async React fare state stale vs explicit clicked key (`RETURN_SPLIT_EXPLICIT_KEY_FIX`)

## Exact files changed
- Combined BO-04G engineering: `d3dd484cd65f1a2a372fe6d4cb574316b69efa4e` (41 files)
- **Split return explicit-key fix:** `7459e76b2b52f95912d3cf38d3b2e747745a456f` (`ReturnOptionsPage.tsx` + commerce Playwright matrix)

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

**Split return Book fix (`7459e76b`):** `handleSelect(combo, explicitReturnFareKey?)` — clicked carousel Book key has highest authority; do not wait for `selectedFareByCombo` React commit. Outbound `outbound_fare_option_key` unchanged.

## Tests executed
| Gate | Result |
| --- | --- |
| Laravel CommerceCheckoutSettingsTest | **18 passed / 75 assertions** (prior gate proof; unchanged this pass) |
| Laravel ReturnSplitSelectFlowTest | **9 passed / 48 assertions** |
| Frontend cabin-filter node test | **3 passed** |
| Frontend typecheck | **PASS** |
| Frontend Playwright jp-bo-04g-commerce-matrix | **8 passed** (includes split direct-book race + preselect + OW/paired explicit) |
| Frontend production build | **PASS** |
| Dashboard gate UI / typecheck / live build | **PASS** (prior; no Dashboard runtime change this pass) |

### Split return branded-fare race regression
- Direct Book on `return-flex` without first selecting the card → POST must send `return_fare_option_key=return-flex` (not default `return-basic`) while preserving `outbound_fare_option_key=fare-comfort`
- Preselect-then-Book still submits `return-flex`
- Summary/review fixtures assert Economy Comfort outbound + Economy Flex return brand/price parity
- Flags: `RETURN_SPLIT_DIRECT_BOOK_NON_DEFAULT=PASS`, `RETURN_SPLIT_PRESELECT_THEN_BOOK=PASS`, `RETURN_SPLIT_NO_STALE_STATE_SELECTION=PASS`, `ONE_WAY_EXPLICIT_FARE_BOOK=PASS`, `RETURN_PAIRED_EXPLICIT_FARE_BOOK=PASS`

## Screenshots
`frontend/tmp/jp-bo-04g/playwright/` and `tmp/jp-bo-04g/playwright/`:
- 01-one-way-results.png
- 02-one-way-brand-selected.png
- 04-return-paired-results.png
- 07-return-split-outbound-brand.png
- 10-return-split-direct-book-non-default.png

Remaining matrix shots (review/guest/card) covered by PHPUnit gate matrix; full browser matrix completion deferred to Stage-B live proof with Laravel fixtures.

## Performance evidence
`tmp/jp-bo-04g/jp-bo-04g-performance.json` — Stage-A/04G harness uses **deterministic/fake supplier** responses.

Classification (do not treat `SUPPLIER_FIRST_RESPONSE_P95_MS=0` as live Sabre latency):

| Flag | Value |
| --- | --- |
| `LOCAL_APP_PERFORMANCE` | **PASS** (application-controlled timings meet targets) |
| `LOCAL_SUPPLIER_LATENCY_MEASUREMENT` | **SYNTHETIC_NOT_LIVE** |
| `LIVE_SUPPLIER_PERFORMANCE` | **PENDING_STAGE_B** |

## Migration safety
- File: `database/migrations/2026_08_24_120000_create_commerce_checkout_settings_table.php`
- `MIGRATION_ADDITIVE_ONLY=YES` (create table only)
- `MIGRATION_DESTRUCTIVE_OPERATIONS=0`
- `MIGRATION_ROLLBACK_DEFINED=YES` (`down()` drops table)
- Production defaults preserve current operational behavior:
  - `GUEST_GATE_PRODUCTION_DEFAULT=ON` (`guest_booking_enabled` default `true`; service firstOrCreate also seeds both ON)
  - `CARD_GATE_PRODUCTION_DEFAULT=ON` (`card_payment_enabled` default `true`; AbhiPay still must be active for card UX, but the gate itself defaults ON so existing card flow is not silently disabled)

## Known limitations
- Full 14-shot browser matrix not fully captured in this pass (4 primary commerce shots + PHPUnit gate matrix + Dashboard gate UI proof).
- Live supplier latency not measured — synthetic harness only (`LOCAL_SUPPLIER_LATENCY_MEASUREMENT=SYNTHETIC_NOT_LIVE`).
- Stage B live proof not run.

## Risks
Combined BO-04 + 04G deploy surface is large; requires owner/ChatGPT SHA review before protected deploy.

## Rollback
`git revert 7459e76b` for the split explicit-key fix only; `git revert d3dd484c` (and prior 04A–04F) if rolling back full BO-04G.

## Commit SHAs
| Slice | SHA |
| --- | --- |
| Prior BO-04 engineering (superseded) | `ec9f0ba257a4ef96149bd8474627beec2e2d5a4d` |
| Prior 04G engineering (superseded for deploy) | `d3dd484cd65f1a2a372fe6d4cb574316b69efa4e` |
| **FINAL_ENGINEERING_SHA (JP-BO-04G + split explicit key)** | `7459e76b2b52f95912d3cf38d3b2e747745a456f` |
| Prior verification/docs head | `05891780b876015e0e4a6ac7ffff1dc3cb4a1abb` |
| **FINAL_VERIFICATION_HEAD** | Docs tip of `phase/jp-bo-04` after this summary update |

## Final status
`SOURCE_GREEN=YES` for engineering gates run in this phase.  
`OWNER_RETEST_V3_STATE=BLOCKED_PENDING_COMBINED_STAGE_B_LIVE_PROOF`  
**DO NOT DEPLOY** until owner/ChatGPT independently verifies `FINAL_ENGINEERING_SHA=7459e76b`.  
**DO NOT** treat synthetic `SUPPLIER_FIRST_RESPONSE_P95_MS=0` as live supplier latency.  
Production pin must be the engineering SHA, not the verification/docs tip.
