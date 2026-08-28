# Git report — JP-UX-PORTAL-PERF-01-R2

## Heads (do not confuse)

| Field | Value |
|-------|-------|
| BRANCH | `phase/jp-flight-perf-01` |
| START_BRANCH_HEAD (R2 start docs) | `5471d19f1ac0f62f1d0d408c8b333150de9c9cf0` |
| START_ENGINEERING_SHA | `9f5b70f45228ae333495afd0d941467676fd488f` |
| FINAL_ENGINEERING_SHA | `7c923e3294910fc122a4776bb13d9146c5e36559` |
| DEPLOYED_RUNTIME_SHA | `7c923e3294910fc122a4776bb13d9146c5e36559` |
| EVIDENCE_COMMIT_SHA | *(this docs commit — set after push)* |
| FINAL_BRANCH_HEAD_SHA | *(branch tip after evidence commit)* |
| PUBLIC_BUILD_ID | `aq1S4pcjSXLbIEboahxkQ` |

## Ancestry

```
562ebe0e → 9f5b70f4 (runtime) → 4609f93e (evidence) → 5471d19f (docs pin)
→ 99270933 → e4d36539 → a638b678 → 7c923e32 (final engineering)
```

## Runtime commits

| SHA | Deployed | Purpose |
|-----|----------|---------|
| `99270933` | superseded | Drawer seed + group SSR |
| `e4d36539` | superseded | Warm-start revalidation |
| `7c923e32` | YES | Soft-nav passenger handoff (includes prior) |

Never amend deployed history. Docs/evidence remain separate from engineering.
