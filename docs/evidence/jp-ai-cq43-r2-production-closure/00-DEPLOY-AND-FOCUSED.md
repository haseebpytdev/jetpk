# CQ43-R2 production closure — deploy + focused gates

## Merge

| Field | Value |
| --- | --- |
| PR | #42 |
| Reviewed head | `d1f72ee565afc81285420fd9c0e1d8f22e708f83` |
| Base | `cdb4637c83aa927be759fb512ba7868e8bc1d296` |
| Merge strategy | GitHub squash merge |
| MERGE_SHA / MAIN_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| PR42_MERGED | YES |

## Deploy

| Field | Value |
| --- | --- |
| PREVIOUS_RUNTIME_SHA | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| DEPLOYED_RUNTIME_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| DEPLOY_MARKER | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| FRONTEND_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| APPLICATION_CODE_PARITY | PASS |

## Config parity

```
CONVERSATIONAL_ENABLED=true
SEMANTIC_PLANNER_ENABLED=true
SEMANTIC_COMPOSER_ENABLED=false
AI_EMBED_ENABLED=false
brain_enabled=true
COMPOSER=OFF
IFRAME=OFF
```

## Focused results

- In-proc focused closure: `out/SUMMARY.json` → `CQ43_R2_PRODUCTION_CLOSURE_INPROC=PASS`
- Browser same-body/distinct-ID: `out/browser-same-body-identity.json` → PASS
- Browser continuity (≥15 turns): `out/browser-continuity-soak.json` → PASS

`CQ43_R2_PRODUCTION_CLOSURE=PASS`
