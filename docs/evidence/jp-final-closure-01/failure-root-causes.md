# Failure / root causes — JP-FINAL-CLOSURE-01 (CURRENT = R6)

Historical R1 checkpoint preserved as `failure-root-causes-r1-checkpoint.md`.

## Resolved history (not current blockers)

| Area | Status | Notes |
|---|---|---|
| Checkout SMTP → HTTP 500 | RESOLVED | Best-effort verification mail |
| Email hardcode / semantic live render | RESOLVED (R4) | |
| Groups Hero / manual_local E2E | RESOLVED | JFZZT2DJ / WZBJCK6Z preserved |
| Flight card CTA code parity | RESOLVED | Shared `FlightResultActions` |
| Pair + segmented outbound visuals | RESOLVED (R5/R6) | |
| Segmented return card + Details | RESOLVED (R6 harness) | `return-option-card` count≥1; Details PASS |
| Auth Customer/Agent/Admin screenshots | RESOLVED (R6) | Staff uses `/staff/dashboard` |
| Group detail 5-viewport | RESOLVED (R6) | `QA-ML-MQVJO8NJ5I` |
| Passengers INFO log discarded | EXPLAINED | `LOG_LEVEL=warning`; fixed via Server-Timing / `X-JP-Passengers-Timing` |
| R5 “disk 100% full” | HISTORICAL | Current ~20% used / ~77G free |

## Current R6 engineering issues

### 1. Book Now → Traveler continuous T0→T9 performance — OPEN (FAIL)

**Measured on deployed `a603211f` / build `38WrCuLnbbv8LChWWw4_M` (n=15/15 successes):**

| Metric | p50 | p95 |
|---|---:|---:|
| SHELL (T0→T8) | 20450ms | 24571ms |
| USABLE (T0→T9) | 20965ms | 25131ms |
| REVALIDATION | 1351ms | 2156ms |
| SERVER_PASSENGERS | 166ms | 541ms |
| SESSION_HYDRATE | 0ms | 0ms |
| OFFER_RESOLVE | 8ms | 50ms |
| OUTLIER_COUNT_OVER_15S | 11 | |

**Expanding interval:** T7→T8 (hard `location.assign` → Traveler shell mark), bimodal ~1.1s vs ~15–22s.

**Not dominant:** revalidation (~1.2–2.1s), passengers API (~150–540ms), session hydrate (~0).

**Empty-page HTTPS TTFB** for `/booking/passengers` probed ~70–120ms — HTML is fast; stall is post-navigation JS/hydration readiness under results→checkout transition.

**Shipped R6 mitigations:** checkout `(checkout)` group; anonymous layout; hard-nav; Server-Timing headers; wall-clock timing restore. R5B Promise.race **not** restored.

**Acceptance:** repeated >15s T7→T8 ⇒ `PERFORMANCE=FAIL`.

### 2. Segmented Return Book Now handoff — HARNESS residual

- Visual + Details PASS with `SEGMENTED_RETURN_CARD_FOUND_COUNT≥1`.
- Book → `/booking/passengers` automation intermittent (fare-change “Accept new fare” / search JSON HTML). Product path not freshly proven broken after card+Details success.

### 3. Review responsive matrix — NOT_REACHED safely

- Traveler form screenshots PASS.
- Review not forced: continue would POST passenger data; avoided supplier-adjacent mutation. Classify `REVIEW_RESPONSIVE=NOT_REACHED` (evidence gap), not a proven product layout defect.

## External / non-blocking

- PIA NDC: SUPPLIER_AUTH_REJECT unless new contrary evidence
- Sabre cancel production posture: unchanged
- OS updates / reboot: frozen during R6 (separate maintenance)
- Preview-only email fixtures: HARNESS_FIXTURE

## Commercial safety

- SUPPLIER_MUTATION_CALLS=0, PAYMENT_EXECUTED=NO, TICKET_ISSUED=NO
- LIVE_SUPPLIER_SYNTHETIC_PASSENGER_DATA=0
