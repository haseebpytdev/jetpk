# JP-PERF-FINAL-02 — checkpoint (in progress)

## Authority
- LOCAL/REMOTE: `e6e40d7bddaf5282642b3471a09d14ce76524213` (fare-key alignment)
- PRODUCTION_RUNTIME_SHA: `e6e40d7bddaf5282642b3471a09d14ce76524213` (protected activate PASS)
- PUBLIC_BUILD_ID: `FPUltEF9ZDeW-sG--bR7m`
- Prior builds: `6dfb5b2e`/`mO51f7Q…` → `4c045fe5`/`YKG1i7…` → …
- START_RUNTIME_SHA: `0e385f6bd91d94c6a5ae421983e8c612514d50fc` / build `fpwfYtgf33V0aEA4mN90H`
- Note: traveler harness JSON still hardcodes stale `runtime_sha`/`public_build_id` fields — treat deploy activate log as authority for build identity.

## Phase A — HOST_HEALTH_GATE=PASS
- DISK_USAGE_BEFORE≈97% → AFTER≈18%
- HOST_PRESSURE_CONFIRMED_AS_MAJOR_CONTRIBUTOR=YES
- ROLLBACK_SETS_PRESERVED=2 (latest include `jp-perf-final-02e-…`)

## Phase B1 — PREVALIDATION deployed (preserved)
- FRESH / JOIN sources live; mutations 0 on cert samples

## Phase B2 — in progress
### Soft-nav root causes (fixed)
1. PublicShell remount re-stampeded `PublicRoutePrefetch` RSC
2. Harness opened every nav menu (Flights+Support) before click
3. Laravel `/groups` proxy returned HTML only (stripped RSC) → HARD document nav

### Soft-nav evidence (best)
- Full matrices from cert client are **WAN-volatile**
- Host local Next HTML/RSC ≈5–15ms; disk ~18%; Playwright on host blocked (missing `libatk`)
- Application soft-nav architecture closed; MULTI_SEC=0 full-matrix still needs calm window or host browser deps

### Traveler fare-key fix (deployed `e6e40d7b` / `FPUltEF9…`)
- Align Book Now `fareOptionKey` with `warmStartRevalidation` fallback in FareSelectionPage + FlightDetailsDrawer

### Traveler 02f N=25 valid / 42 attempts (`traveler-warm-final02f-n30.json`)
Evidence: `docs/evidence/jp-app-perf-closure-01/traveler-final02f-console.txt`

| Gate | Result | Limit | Status |
|------|--------|-------|--------|
| ACK P95 | 6 | ≤100 | PASS |
| JP_POST P95 | 489 | ≤500 | PASS |
| NAV_SHELL P95 | 830 | ≤750 | FAIL |
| FETCH P95 | 838 | ≤1000 | PASS (was 2823) |
| CLIENT P95 | 102 | ≤250 | PASS |
| SHELL_USABLE P95 | 404 | ≤1000 | PASS |
| DUP rematch>1 | 3 | 0 | FAIL (was 5) |
| URL authority | PASS | PASS | PASS |
| Mutations | 0 | 0 | PASS |
| FRESH total P95 | 2136 | ≤2000 | FAIL (borderline) |
| FRESH app−supplier P95 | ~820 | — | app not sole driver |
| n valid | 25 | 30 | FAIL (17 search/conn timeouts) |

- Rematch≥2 leftovers are secondary `/laravel/booking/passengers` GETs (not fare-key POST storms); attempt 15 also shows incomplete JOIN revalidate payload + search_id change on secondary fetch.
- Cert window was noisy: many `waitForSelector` search timeouts + `ERR_CONNECTION_TIMED_OUT` to origin.

## FINAL_STATUS
Not closed. Host healthy; prevalidation live; soft-nav architecture fixed; Traveler FETCH gate closed after fare-key fix; remaining open: DUP=3, NAV_SHELL P95 830, FRESH P95 2136, n=25.
