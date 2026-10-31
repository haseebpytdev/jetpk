# JP-UI-FAB-02 — Post-deploy

## Change

- `frontend/app/globals.css`: `.jp-fab-content-clear` / `.jp-fab-card-clear` → **bottom-only** (no `padding-right`)
- Search CTAs (`OneWayForm` / `ReturnForm` / `MultiCityForm`): remove `max-lg:w-[calc(100%-4.5rem)]`
- Regression test updated
- Sticky `MobileOrderSummary` right pad retained (action bar vs card narrowing)

## Deploy

| Field | Value |
|---|---|
| Branch SHA | d9e08e18907ebf2b3621d48bc0843588571caa96 |
| FRONTEND_DEPLOY_SHA | d9e08e18907ebf2b3621d48bc0843588571caa96 |
| FINAL_DEPLOY_STATUS | PASS |
| Note | `deploy-sha.txt` marker rewritten to FE SHA by finalize script (marker ≠ Laravel file parity) |

## LIVE mobile 390 measures (post)

| Page | HORIZONTAL_OVERFLOW | padRight on fab-clear | leftGap | rightGap |
|---|---|---|---|---|
| /login | 0 | **0px** | 23 | 38 |
| / | 0 | n/a | 39 | 54 |
| /groups/search | 0 | n/a | 16 | 31 |
| /login@1440 | 0 | — | — | — |

```text
UNNECESSARY_RIGHT_CONTENT_RESERVATION=0
HORIZONTAL_OVERFLOW=0
FAB_02_FIXED=YES
FAB_02_DEPLOYED=YES
MOBILE_LAYOUT_CERTIFIED=PASS (core pages; residual asymmetry from layout chrome, not FAB padding-right)
```
