# Failure / root causes — JP-FINAL-CLOSURE-01 (CURRENT = R5)

Historical R1 checkpoint preserved as `failure-root-causes-r1-checkpoint.md`.

## Resolved history (not current blockers)

| Area | Status | Notes |
|---|---|---|
| Checkout SMTP → HTTP 500 | RESOLVED | Best-effort verification mail; R1/R2 |
| Email hardcode / semantic live render | RESOLVED (R4) | `unresolved_live_render_replace_with_resolver=0` |
| Groups Hero / manual_local E2E | RESOLVED | JFZZT2DJ / WZBJCK6Z preserved |
| Flight card CTA code parity | RESOLVED (R4) | Shared `FlightResultActions` |
| Pair visual proof gap | RESOLVED (R5 harness) | Requires `view=pair` (not `return_view`); ISB–DXB inventory |
| Auth dashboard redirect-as-proof | PARTIAL (R5) | Customer + Agent authenticated PASS; Staff uses `/staff/dashboard` |

## Current R5 engineering issues

### 1. Book Now → Traveler performance — OPEN (FAIL)

- **Deployed measured (SHA `3c0def3a`, build `PGOVQaS2ow-r7q2OHoNdo`):**
  - n=14 attempted, **13 successes**
  - SHELL p50=**2740ms**, p95=**33888ms**
  - USABLE p50=**3693ms**, p95=**34480ms**
  - REVALIDATION p50≈1.4s (not dominant)
- **Standard median** used (even-n average of middle two); do not use R4’s incorrect 4th-of-8 “p50”.
- **Dominant remaining defect:** rare soft-nav stalls (T7→T8 ~30s) while most samples are ~2.5–4s.
- **Fixes shipped / staged:**
  - Preserve draft `search_id`; skip duplicate Sabre validate after recent revalidation; release session before offer I/O; passengers S0–S8 timing (`3c0def3a`)
  - Public layout session/config hard timeout + draft `offer_freshness` merge on revalidate (`cf03d5cc` **ACTIVATE=PASS**, build `3JiCRsBEwJwCFd3-b-GvE`)
- **Acceptance:** p50 meets targets on R5A sample; **p95 spike >15s ⇒ PERFORMANCE=FAIL** until post-R5B resample clears outliers.

### 2. Segmented return visual / Book action proof — OPEN

- Outbound card DOM proven.
- Return-leg harness often stayed on segmented results URL (`return-option-card` not reached) when Continue→`/flights/return-options` did not complete in time.
- Product code path exists (`legMode=outbound_confirm` → `/flights/return-options`); treat as **harness/flow evidence gap** unless a product regression is freshly proven.

### 3. Review / full traveler matrix depth — INCOMPLETE

- Customer/Agent dashboards authenticated screenshots PASS.
- Staff/Admin: use `/staff/dashboard` (not `/admin` for QA staff account).
- Full Review matrix + all 5 viewports for Traveler/Group Detail may still be thin in R5 pack.

## External / non-blocking

- Production disk was **100% full** (73G backups) blocking R5b backup; pruned old packs to restore ~17G free. Monitor disk.
- Sabre cancel production posture: unchanged (owner decision).
- PIA NDC: SUPPLIER_AUTH_REJECT unless new contrary evidence.
- Preview-only `JetpkEmailSampleDataProvider`: **HARNESS_FIXTURE**, not required runtime.

## Commercial safety (unchanged requirement)

- SUPPLIER_MUTATION_CALLS=0, PAYMENT_EXECUTED=NO, TICKET_ISSUED=NO
- LIVE_SUPPLIER_SYNTHETIC_PASSENGER_DATA=0
