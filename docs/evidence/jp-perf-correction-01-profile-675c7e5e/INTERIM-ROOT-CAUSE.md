# PERF-CORRECTION-01 — interim root-cause (pre-deploy)

Pinned FAIL baseline: `docs/evidence/jp-final-perf-cert-675c7e5e/`  
`BASELINE_EVIDENCE_COMMIT=8c2795ff319c43381e4f6439ad98101aca57ca7a`  
`BASELINE_SAMPLE_ROWS=280`  
`BASELINE_CERTIFICATION=FAIL`

## Provisional dominant stages (from FAIL pack + code)

### RETURN_PAIR
- **Dominant:** CLIENT post-supplier — HTTP `/results/data` ready → first `pair-return-card` (not supplier).
- **Evidence:** POST_SUPPLIER P95=1509; in-app `__jpD2rFlushMs` historically ~200ms when measured from state flush; alternate-view `/results/data?view=` prefetch was competing immediately after first rows.
- **RETURN_PAIR_DOMINANT_STAGE=BROWSER_RESPONSE_END→FIRST_PAIR_CARD_VISIBLE (contended by alt-view prefetch)**
- **RETURN_PAIR_STAGE_P95≈1509ms (baseline gate metric)**

### TRAVELER
- Cert path uses `use-revalidation` (already primed). APP = usable − revalidateEnd includes **awaited pre-nav prime** + hard nav + form.
- Samples with passengersMs~1s imply session prime miss or post-nav fetch still occurring on some samples.
- **TRAVELER_DOMINANT_STAGE=REVALIDATE_RESPONSE→TRAVELER_SHELL (prime await + hard-nav + passengers hydrate)**
- **TRAVELER_STAGE_P95=2967ms (baseline APP)**

### PRIVACY / SOFT NAV
- `generateMetadata` + page both called `getPrivacy()`; layout + root call `getConfig()`.
- `fetchWithTimeout` used unique AbortController → broke Next fetch dedupe.
- **PRIVACY_BACKEND_CALL_COUNT=expected 2× managed page + N× config before fix (to confirm on profile)**
- **PRIVACY_DOMINANT_STAGE=LARAVEL_CMS + RSC waterfall / cold hydration**
- **PRIVACY_STAGE_P95=4435ms (baseline APP home_privacy)**

## Fixes landed (engineering; not yet production-recertified)

| Commit | Change |
|---|---|
| `8c2795ff` | FAIL baseline evidence pin |
| `eca94eb5` | Traveler prime wire + overlap; React `cache()` + AbortSignal fix for CMS/config |
| (tip) | Defer alt-view results prefetch past first card |

## Next (blocked until production deploy of tip SHA)

1. Complete stage profile JSON under `docs/evidence/jp-perf-correction-01-profile-675c7e5e/`
2. Deploy tip → new BUILD_IDs
3. Diagnostic N=5–10 then full unbiased recert into `docs/evidence/jp-final-perf-cert-<NEWSHA>/`
4. Do not overwrite FAIL baseline directory
