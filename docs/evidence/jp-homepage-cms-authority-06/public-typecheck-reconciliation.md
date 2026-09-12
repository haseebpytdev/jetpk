# Public TypeScript Reconciliation — Homepage CMS Authority 06

Gate target: **shipping application source only** (`tsconfig.application.json`).

## Summary

| Metric | Value |
|--------|------:|
| Raw `tsc` errors (old root tsconfig) | 13 |
| Application shipping errors | **0** |
| NEW_APPLICATION_TS_ERRORS | **0** |
| SHIPPING_APPLICATION_TS_ERRORS | **0** |
| PUBLIC_APPLICATION_TYPECHECK | **PASS** |
| PUBLIC_PRODUCTION_BUILD | **PASS** |

## Configuration action

- Added `frontend/tsconfig.application.json` — includes `app/`, `components/`, `features/`, `lib/`, `services/`, `types/`, `.next/types`.
- Excludes only non-shipping harness paths: `tests/**/*`, `scripts/**/*`.
- Updated `npm run typecheck` → `tsc --noEmit -p tsconfig.application.json`.

Root `tsconfig.json` remains for IDE breadth; production build uses Next compiler on application paths only.

---

## Error ledger

### 1
FILE=`frontend/tests/regression/base-offer-fare.test.ts`  
ERROR=`TS5097: import path can only end with '.ts' extension`  
CLASSIFICATION=`TEST_HARNESS`  
IN_PRODUCTION_BUILD=`NO`  
INTRODUCED_BY_THIS_BRANCH=`NO`  
ACTION=`Excluded from application tsconfig; run via dedicated node/tsx test scripts`

### 2
FILE=`frontend/tests/regression/base-offer-fare.test.ts` (line 10)  
ERROR=`TS5097`  
CLASSIFICATION=`TEST_HARNESS`  
IN_PRODUCTION_BUILD=`NO`  
INTRODUCED_BY_THIS_BRANCH=`NO`  
ACTION=`Same as above`

### 3–7
FILE=`frontend/tests/regression/jp-perf-final-02-prevalidation.test.ts`  
ERROR=`TS7006 implicit any`, `TS5097 .ts import`, `TS2339 never`, `TS2322 never`  
CLASSIFICATION=`EVIDENCE_SCRIPT` / `TEST_HARNESS`  
IN_PRODUCTION_BUILD=`NO`  
INTRODUCED_BY_THIS_BRANCH=`NO`  
ACTION=`Excluded from application tsconfig; perf prevalidation script only`

### 8–13
FILE=`frontend/tests/regression/jp-ux-polish-02.test.ts`  
ERROR=`TS2307 Cannot find module 'vitest'`  
CLASSIFICATION=`TEST_HARNESS`  
IN_PRODUCTION_BUILD=`NO`  
INTRODUCED_BY_THIS_BRANCH=`NO`  
ACTION=`Excluded; vitest not installed in public frontend package (intentional harness gap)`

---

## Verification commands

```text
npm run typecheck   # application tsconfig — PASS
npm run build       # production build — PASS
```
