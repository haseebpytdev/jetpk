# PERF-CORRECTION-01 Profile — SHA `675c7e5e` / Build `FfNP1fgjiB4_gK6lNt4FI`

**MEASUREMENT_METHOD:** Same-SHA production FAIL cert evidence (`docs/evidence/jp-final-perf-cert-675c7e5e/`). Live Playwright network re-instrumentation blocked at profile time; stage dominance inferred from cert percentiles + code.

**Note:** Product fixes + deploy to `36221ac0` / `H9TYWa1Q2VFnwaR4TlPZy` landed *after* this baseline profile. Do not treat this file as post-fix proof.

---

## ROOT-CAUSE BREAKDOWN

```
RETURN_PAIR_DOMINANT_STAGE=CLIENT
RETURN_PAIR_STAGE_P95={"post_supplier_to_card_visible_ms":1509,"d2r_flush_ms":208,"supplier_search_ms":7825,"browser_goto_to_card_ms":6083,"duplicate_supplier_searches":0,"N":30}

TRAVELER_DOMINANT_STAGE=APP_POST_REVALIDATE
TRAVELER_STAGE_P95={"revalidate_ms":2628,"app_post_revalidate_ms":2967,"passengers_first_get_ms":1714,"raw_click_to_usable_ms":5512,"redundant_revalidation":0,"N":30}

PRIVACY_BACKEND_CALL_COUNT={"managed_page_browser":null,"public_config_browser":null,"total_public_backend_browser":null,"note":"NOT_INSTRUMENTED_live; code inferred SSR privacy×2 + config×N before cache/AbortSignal fix"}
PRIVACY_DOMINANT_STAGE=RSC_CLIENT_TRANSITION
PRIVACY_STAGE_P95={"soft_nav_app_ms":4435,"soft_nav_p50_ms":1596,"N":20,"route":"home_privacy"}
```

---

## 1) RETURN PAIR — POST_SUPPLIER

| Metric | P50 | P95 | N |
|---|---:|---:|---:|
| Post-supplier → `pair-return-card` | 766 | **1509** | 30 |
| `__jpD2rFlushMs` | 60 | **208** | 20 |
| Supplier search (seed) | — | 7825 | 30 |

**SERVER vs CLIENT:** CLIENT dominant. Post-supplier P95 ≈7× flushSync P95.  
**DUPLICATE_SUPPLIER_SEARCHES=0**

---

## 2) TRAVELER

| Stage | P95 | N |
|---|---:|---:|
| Revalidate-offer | 2628 | 30 |
| App post-revalidate | **2967** | 30 |
| First `/booking/passengers` GET | 1714 | 30 |

Cert Book Now path used `use-revalidation` (already primed). `use-offer-selection` lacked prime at profile time — **wired in PERF-CORRECTION-01 tip**.  
**PASSENGERS_POST_NAV_FETCH_COUNT:** not instrumented in FAIL harness.

---

## 3) PRIVACY soft-nav

| Metric | P50 | P95 | N |
|---|---:|---:|---:|
| `home_privacy` APP | 1596 | **4435** | 20 |

Dominant: Next soft-nav RSC/client transition (wall >> small JSON). Server double-fetch of managed page + AbortSignal breaking Next dedupe addressed in tip.

---

## Safe recommendations (executed in tip `36221ac0`)

1. RETURN: defer alt-view `/results/data` prefetch past first card  
2. TRAVELER: prime in offer-selection; overlap prime with warm on revalidation path  
3. PRIVACY/CMS: React `cache()` + skip AbortSignal on server `next.revalidate` fetches  

Full unbiased recert must use new evidence dir under `jp-final-perf-cert-36221ac0/` — never overwrite this FAIL baseline.
