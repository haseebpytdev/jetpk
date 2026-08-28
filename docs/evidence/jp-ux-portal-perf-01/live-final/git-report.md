# Git report — JP-UX-PORTAL-PERF-01

## Heads

| Field | Value |
|-------|-------|
| START_BRANCH | `phase/jp-flight-perf-01` |
| START_DOCS_HEAD | `562ebe0e125298d7074b159f359b765c3a83b8cf` |
| START_RUNTIME_SHA | `9979330c35141bc85cd5db7941f4a9c274e89a52` |
| FINAL_BRANCH | `phase/jp-flight-perf-01` |
| FINAL_ENGINEERING_SHA | `9f5b70f45228ae333495afd0d941467676fd488f` |
| FINAL_DEPLOYED_SHA | `9f5b70f45228ae333495afd0d941467676fd488f` |
| FINAL_DOCS_SHA | *(set after docs commit)* |

## Ancestry

```
9979330c fix(flights): skip non-flight SupplierConnection rows…
b147f548 docs(flights): commit JP-FLIGHT-PERF-01-R2 live-final evidence
562ebe0e docs(flights): reopen perf proof and classify PIA NDC AUTH failure
9f5b70f4 fix(public): close owner flight UX and customer portal defects
```

Proven: `9979330c → b147f548 → 562ebe0e → 9f5b70f4`

## Commits in wave

| SHA | TYPE | SUBJECT | DEPLOYED? | PURPOSE |
|-----|------|---------|-----------|---------|
| `9f5b70f4` | runtime | fix(public): close owner flight UX and customer portal defects | YES | Dashboard contract, footer, cards, return view modal, review/traveler shell, perf waterfalls |

## Runtime diff `9979330c..9f5b70f4`

23 files (22 deployable + 1 PHPUnit contract test). No `.env`, credentials, dumps, or `node_modules`.

## Force push / destructive ops

None.
