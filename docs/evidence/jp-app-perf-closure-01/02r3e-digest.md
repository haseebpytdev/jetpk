# JP-PERF-FINAL-02R3E digest

Local-only raw N=30 (not in Git; contains offer ids / POST previews):

`docs/evidence/jp-app-perf-closure-01/traveler-warm-final02r3-n30.json`

SHA256=`5ec89825d556287e2c9b0d86ed5d5166bd7f56127af30cc231a349a1421c1a52`
BYTES=256944
RUNTIME=`996b62d1db34bedb1f5906b51b93e96949ab4743`
BUILD=`5GNVRs0UtH2hjOKBjoeC6`
N=30 MIXED_BUILD=0 TOTAL_RECONCILED=YES SUPPLIER_MUTATION_CALLS=0

## Production parity (2026-09-05)

REMOTE_HEAD=`996b62d1db34bedb1f5906b51b93e96949ab4743` (`jetpk/phase/jp-flight-perf-01`)
LOCAL_HEAD=`996b62d1db34bedb1f5906b51b93e96949ab4743`
PRODUCTION_RUNTIME_SHA=`996b62d1db34bedb1f5906b51b93e96949ab4743`
PRODUCTION_BUILD_ID=`5GNVRs0UtH2hjOKBjoeC6`

## FRESH P95 (sample `return-fare-final02-25`, parent=3149, residual=0)

Exclusive intervals (same sample, not subtracted P95s):

| bucket | ms | class |
|---|---:|---|
| BOOK_NOW_TO_NAV | 148 | JETPAKISTAN_CLIENT |
| NAV_TO_SHELL (HARD_ASSIGN document) | 727 | EXTERNAL_NETWORK (per-layer DNS/TCP/TLS not in harness; origin passengers TTFB~22ms proves this bucket is not origin PHP/Next) |
| SHELL_TO_PASSENGERS_REQUEST | 698 | JETPAKISTAN_CLIENT |
| PASSENGERS server (`x-jp-passengers-timing`) | 444 | JETPAKISTAN_SERVER |
| PASSENGERS transport (network−server) | 805 | EXTERNAL_NETWORK |
| CLIENT_PROCESS | 327 | JETPAKISTAN_CLIENT (this sample also ran safe auto-reprice) |
| **sum** | **3149** | |

FRESH cohort P95 of per-sample sums: EXTERNAL=1775 APPLICATION=1617 UNATTRIBUTED=0 (max abs residual 0).

Harness does not record DNS/TCP/TLS/PARSE/HYDRATION inside NAV_TO_SHELL. That 727ms remains one EXTERNAL_NETWORK bucket.

## Shell→usable

Harness `SHELL_TO_USABLE_APP_MS` = SHELL_TO_REQUEST + CLIENT (excludes passengers WAN).

RAW wall P95=3120
EXTERNAL component P95 (raw−app per sample)=1580
APP P95 all samples=1025 (driven by fallback sample 25: 698+327)
APP P95 when Traveler reprice=0 (28/30)=852

## 2/30 non-authoritative FRESH

`return-fare-final02-19` and `-25`: Book Now `success`, `FARE_AUTHORITY_PRESERVED=YES`, same `lt-pi0`, `price_needs_refresh=true`, `authoritative_after_revalidation=false`. Response keys include `requires_fare_change_acceptance` but the boolean was not stored. Traveler POST=1 (required fallback). JOINED 10/10 authoritative. Classify both as AUTHORITY_PERSISTENCE_LOST-unproven (possible pending-acceptance); skip logic did not skip. No code change.

## PHPUnit boot

`ClientManagedPageReservedSlugs` exists at `app/Support/Client/ClientManagedPageReservedSlugs.php`. Local `php artisan test` resolved `C:\Users\khadi\ota\vendor` (wrong tree). Baseline/unrelated to `996b62d1`. Compensating: 9/9 TS skip-authority tests; live passengers JSON flags on 28/30.

## Ordinary CLIENT_SOFT (targeted N=10, build probe 2026-09-05, reload=0)

See `site-soft-nav-decompose-02r3e.json`. APPLICATION_CONTROLLED_P95 = usable−last_rsc_duration. All <1500. MULTI_SECOND app count=0.
