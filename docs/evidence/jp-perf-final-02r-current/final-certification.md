# JP-PERF-FINAL-02R — Final Certification (Current Runtime)

**Measured:** 2026-09-09  
**Branch checkpoint:** `58fdb4a83273397559a1330695df5c70861710aa`  
**Runtime:** `f039bef3dda1320c08fdccb4633d5c7c34b3b61e`  
**Public build:** `m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw`

## Parity (resume verified)

| Gate | Value |
|---|---|
| REMOTE_HEAD | 58fdb4a83273397559a1330695df5c70861710aa |
| PRODUCTION_SHA | f039bef3dda1320c08fdccb4633d5c7c34b3b61e |
| PUBLIC_BUILD_ID | m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw |
| LIVE_HTTP | 200 |
| HOST_HEALTH_GATE | PASS |
| RUNTIME_OWNERSHIP_GATE | PASS |
| ROOT_OWNED_RUNTIME_FILES | 0 |

Return cohort preserved: `return-n30.json` SHA256 `B4BF43DBC3ECAB4F5FB7AEC7B43FB80BD0D2613A71AFFD016F86C0D4E831C9A5` (not rerun).

---

## JETPAKISTAN_JP_PERF_FINAL_02R

```
PRODUCTION_SHA=f039bef3dda1320c08fdccb4633d5c7c34b3b61e
PUBLIC_BUILD_ID=m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw

RETURN_N=30
RETURN_P50=3866
RETURN_P95=5622
RETURN_APP_P95=4093
RETURN_SUPPLIER_P95=3652
RETURN_EXTERNAL_P95=(not isolated — supplier wall time embedded in pre-pair interval)
RETURN_DUPLICATES=0
RETURN_GATE=FAIL

TRAVELER_N=30
TRAVELER_RAW_P95=9628
TRAVELER_APP_P95=3747
TRAVELER_SERVER_P95=4689
TRAVELER_EXTERNAL_P95=786
TRAVELER_REDUNDANT_POSTS=0

SOFT_NAV_ROUTES=16 (10 required + 6 supplementary)
SOFT_NAV_WORST_APP_P95=2720
APP_MULTI_SECOND_ROUTE_COUNT=3

TOTAL_RECONCILED=PARTIAL (Return decomposition); YES (Traveler cohort)
UNEXPLAINED_SAMPLES=6 (Return >4500ms outliers with residual unattributed_ms)
DUPLICATE_SUPPLIER_CALLS=0
SUPPLIER_MUTATION_CALLS=0

CODE_CHANGES_REQUIRED=YES (documented — no deploy this pass)
NEW_ENGINEERING_SHA=(unchanged — no code deploy)
DEPLOYED=NO

RUNTIME_OWNERSHIP_GATE=PASS
PERF_FINAL_VERIFIER=FAIL

STATUS=FAIL
BLOCKERS=Return absolute P95=5622; Traveler APP P95=3747>2000; Passengers server P95=4689; Soft-nav 3 routes APP>1500; Return layer reconciliation PARTIAL; groups detail HARD nav 2720ms
```

---

## Cohort evidence files

| Artifact | Path |
|---|---|
| Return N=30 | `return-n30.json` |
| Return decomposition | `return-decomposition-summary.json`, `return-outliers.md` |
| Traveler N=30 (fresh cohort) | `traveler-n30.json`, `traveler-final-run.log` |
| Soft-nav matrix | `soft-nav-matrix.json`, `soft-nav-run.log` |
| Server timings | `server-timings.json` |
| Supplier reconciliation | `supplier-reconciliation.json` |
| UX smoothness | `ux-smoothness.json` |
| Grok verifier | Independent FAIL — see session transcript |

---

## Return outlier classification (>4500ms)

Six samples: **3 supplier-dominant**, **3 JP post-supplier-dominant**. P95 sample `return-browser-25` (5622ms): supplier 2963ms, post-supplier→usable 2606ms, loading_shell 2832ms. `RETURN_POST_SUPPLIER_TO_USABLE_P95≈2024ms` exceeds 1000ms application threshold.

---

## Traveler notes

- Fresh cohort (N=20): `FRESH_APP_P95=813` (passes ≤2000ms target)
- Joined-inflight cohort (N=10): P95=9748 (supplier overlap path)
- Zero duplicate revalidation, zero mutations, URL authority PASS

---

## Soft-nav required routes

All 10 required transitions measured at N=20. Failures vs ≤1500ms APP: `home_to_register` 1601, `register_to_login` 1693, `groups_to_group_detail` 2720 (HARD document nav).

---

## Decision gate

**No application code changes or deploy in this pass.** Root causes are mixed supplier latency + JP post-supplier shell/render delay (Return), passengers-origin server time (Traveler), and register-route client work (soft-nav). Next phase should target smallest fixes with scoped rerun on affected cohorts only.

---

## Tests / commands run

- Traveler N=30: `node frontend/scripts/jp-perf-final-02r-current/run-traveler-n30.mjs`
- Soft-nav N=20×16: `node frontend/scripts/jp-perf-final-02r-current/run-soft-nav-matrix.mjs`
- UX: `node frontend/scripts/jp-perf-final-02r-current/run-ux-smoothness.mjs`
- Server probe via SSH SCP + `/tmp/jp-perf-02r-probe.sh`
- Grok verifier: FAIL

No SFTP upload. No production mutation.
