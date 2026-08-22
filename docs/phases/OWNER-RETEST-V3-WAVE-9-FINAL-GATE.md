# OWNER-RETEST-V3 WAVE-9 — FINAL ENGINEERING GATE

## Status
`OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED` (payment-safety engineering closed; **DO NOT DEPLOY**; do not mark OWNER_RETEST_V3 PASS)

Engineering loop complete for AbhiPay v3 amount-unit contract. **DO NOT DEPLOY** until owner authorizes after checkout config is available.

## Gate checklist

| Gate | Result |
|---|---|
| REVIEW_SELECTED_FARE_PARITY | PASS |
| REVIEW_SELECTED_BAGGAGE_PARITY | PASS |
| REVIEW_SELECTED_PRICE_PARITY | PASS |
| REVIEW_ITINERARY | PASS |
| REVIEW_PASSENGERS | PASS |
| REVIEW_CONTACT | PASS |
| REVIEW_EDIT_PASSENGERS | PASS |
| REVIEW_NULL_TITLE | FIXED |
| ORDER_SUMMARY_UI | PASS |
| ORDER_SUMMARY_PRICE_PARITY | PASS |
| PAYMENT_MANUAL_OPTION | PASS |
| PAYMENT_CARD_OPTION | PASS_WHEN_CONFIGURED |
| ABHIPAY_EXISTING_MODULE_REUSED | YES |
| ABHIPAY_V3_CONTRACT | PASS |
| ABHIPAY_V3_AMOUNT_MAJOR_UNITS | PASS |
| ABHIPAY_100X_AMOUNT_REJECTED | PASS |
| ABHIPAY_CURRENCYTYPE_VERIFIED | PASS |
| ABHIPAY_AMOUNT_PARITY | PASS |
| ABHIPAY_IDEMPOTENCY | PASS |
| ABHIPAY_CLIENT_TRANSACTION_ID | PASS |
| ABHIPAY_BY_RRN_VERIFY | PASS |
| PAYMENT_SECRET_EXPOSURE | 0 |
| MANUAL_CTA | PASS (`Confirm booking`) |
| CARD_CTA | PASS_WHEN_CONFIGURED (`Continue to payment`) |
| TYPECHECK | PASS |
| LARAVEL_TESTS | PASS (AbhiPayGatewayTest 14/14; Wave-9 related 96/96) |
| PLAYWRIGHT | PASS (Wave-9E visual matrix retained; no FE change in payment-safety commit) |
| FRONTEND_BUILD | PASS |
| SOURCE_GREEN | YES |
| TESTS_GREEN | YES |
| VISUAL_GREEN | YES |
| GIT_0_0 | YES (after eng + docs pushes) |

## SHA pins (do not confuse docs with engineering)

| Pin | Value |
|---|---|
| Production base SHA | `8cf657d7d35cc97848318f56184825ac49af6225` |
| `FINAL_WAVE9_ENGINEERING_SHA` | `67417d225fcd70e8e8cb1a1b535ec0ed8eee0877` |
| Prior eng (pre payment-fix) | `1c846fa8438483aea213400bbba26760aecfee1a` |
| Docs-only (not ENGINEERING) | `dfc70e74…`, `10392382…`, `8db2fe84…` |

`PREDEPLOY_DOCS_HEAD` / `CURRENT_BRANCH_TIP` = tip after the docs/manifest commit (not an engineering SHA).

Exact runtime paths: `docs/phases/OWNER-RETEST-V3-WAVE-9-RUNTIME-MANIFEST.md`  
`EXACT_RUNTIME_FILE_COUNT=11`

## Runtime delta (deploy preparation only — not executed)

```text
8cf657d7d35cc97848318f56184825ac49af6225
→ 67417d225fcd70e8e8cb1a1b535ec0ed8eee0877
```

Use protected JetPakistan deploy scripts only after explicit owner authorization.

## Production AbhiPay (read-only)

```text
ABHIPAY_CHECKOUT_AVAILABLE=NO
```

Reason: no AbhiPay `payment_gateways` row. Do not fabricate Pay by Card until configured.

## Visual proof
`tmp/owner-v3-flight-wave-9/` — 01..16 PNGs + `VISUAL-MATRIX-INDEX.md`

## Commercial safety
No live PNR / hold / AbhiPay order / ticket / payment created during engineering.
