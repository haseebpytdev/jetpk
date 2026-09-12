# JP-CANONICAL-RECONCILIATION-CORRECTION-01

**Closed:** 2026-09-12  
**Branch:** `phase/jp-master-unfinished-closure-10`

## Problem

Canonical merge `fafb6c18` unintentionally promoted Auth-C experiment `f5c8a9b9` (`AuthRouteLayoutShell`), which Authority-06 closure explicitly marked **NOT DEPLOYED** / deferred to `JP-SOFT-NAV-PERF-01`.

Certified Authority-06 production reference: `8c50fc61967e51c5b576375b55ae7701d122c121`.

## Correction

Restored `frontend/app/(auth)/layout.tsx` to certified `PublicShell` + `ANONYMOUS_SESSION` + `AuthCsrfBootstrap`. Removed `AuthRouteLayoutShell.tsx` (no other consumers).

## Deferred experiment audit (post `a676487e`)

| Commit | Classification |
|---|---|
| `f5c8a9b9` | **DEFERRED_NOT_FOR_PRODUCTION** — reverted |
| `1d98b296` | **TEST_ONLY** — retained |
| `67510da4` | **DOCS_ONLY** — retained |

```
OTHER_ACCIDENTALLY_PROMOTED_RUNTIME_EXPERIMENTS=0
AUTH_C_DEFERRED_EXPERIMENT_REMOVED=YES
```

## Regression gates

| Gate | Result |
|---|---|
| `git diff --check` | PASS |
| Frontend typecheck | PASS |
| Frontend production build (local) | PASS |
| AUTH_PLAYWRIGHT (12 tests) | PASS |
| LOGIN / REGISTER / AGENT_REGISTER | PASS |
| LOGGED_OUT_HEADER | PASS |
| AUTH_CSRF_BOOTSTRAP | PASS |

## Deployment

| Field | Value |
|---|---|
| PREVIOUS_CANONICAL_HEAD | `fc355425c4946586ea6b318799d4c78a003c297a` |
| PREVIOUS_PRODUCTION_SHA | `fafb6c18899e719cc89bc2d153cda8550a7d6101` |
| CORRECTIVE_ENGINEERING_SHA | `a7ea9fcfee6068f28a3735c53ad4aa105a93abf2` |
| PRODUCTION_SHA | `a7ea9fcfee6068f28a3735c53ad4aa105a93abf2` |
| Release | `jetpk-20260912T110410Z` |
| Backup | `20260912T105427Z` |
| PUBLIC_ONLY | YES |
| PUBLIC_BUILD_ID | `rbyyI_4APbYPWOA8ezwsX` |
| DASHBOARD_BUILD_ID | `Oe-zJL6LiBgWdhTly_96u` (unchanged) |
| PRE_PROXY_GATE | PASS |
| PUBLIC_HTTP | 200 |

## P2 hygiene disposition

| Path | Classification |
|---|---|
| `tmp/analyze_diag.py`, `jp-ai-lab-diagnostic-repro.py`, `recover_original_corpus.py`, `extract_original_corpus.py` | **ACTIVE_LOCAL_DIAGNOSTIC** — untracked, not committed |
| `tmp/r4-diagnostic-100*.json`, `jp-ai-lab-closure-r42.sh` | **IGNORE** — local AI-lab artifacts |
| Remaining `tmp/*` outputs | **IGNORE** — generated local evidence outside repo scope |

```
LOCAL_UNTRACKED_DIAGNOSTICS_OUTSIDE_REPO_SCOPE=YES
P2_REPOSITORY_HYGIENE=PASS
```

## Status

```
MAIL_SABRE_DEPLOYMENT=CLOSED
GMAIL_REAL_CLIENT_ACCEPTANCE=CLOSED
P0_CANONICAL_RECONCILIATION=PASS
OVERALL=PASS
```
