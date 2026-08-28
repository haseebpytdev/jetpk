# Git report — JP-UX-PORTAL-PERF-01-R3

## Heads (do not confuse)

| Field | Value |
|-------|-------|
| BRANCH | `phase/jp-flight-perf-01` |
| START_DOCS_HEAD | `c0d1d893eeb503681a91928fc0b79c26dc0ccddb` |
| START_RUNTIME_SHA | `d71e065b861657697dc5a58d0d7dc4702f71d373` |
| FINAL_ENGINEERING_SHA | `61362c21907b4e69ac7f399d38943dca2aa2aef4` |
| DEPLOYED_RUNTIME_SHA | `61362c21907b4e69ac7f399d38943dca2aa2aef4` |
| EVIDENCE_COMMIT_SHA | *(set after this docs commit is pushed)* |
| FINAL_BRANCH_HEAD_SHA | *(set after evidence push)* |
| PUBLIC_BUILD_ID | `lhTb3ywP3iwYsjR3lQqZn` |

## Ancestry (runtime path)

```
c0d1d893 (docs; malformed Co-authored-by subject — historical)
  → d71e065b (Draft detail by numeric id)
  → 61362c21 (Draft resume + nearby dates)  ← LIVE RUNTIME
```

Note: branch tip may include later unrelated commits (e.g. groups hero).
R3 **deployed runtime** remains `61362c21` only.

## Runtime commit

| SHA | Deployed | Purpose |
|-----|----------|---------|
| `61362c21` | YES | `fix(customer): resume owned drafts and restore nearby dates` |

Never amend deployed history. Docs/evidence remain separate from engineering.
