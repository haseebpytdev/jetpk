# SERVER_CLEANUP_FUNCTIONAL

**Date:** 2026-09-21  
**Runtime SHA:** `78dadc7b330ca24a6183241458dd8d1f6aa0d454`  
**Public BUILD_ID:** `wmNT0P2lJftqG2n9tM6gp`  
**Dashboard BUILD_ID:** `3TyyvdcNScpOGj0oCkQuT`

## Smoke (non-mutating, canonical host)

| Surface | Result |
|---|---|
| `GET /` homepage | **200** |
| `GET /groups` | **200** |
| `GET /login` | **200** |
| `GET /up` Laravel health | **200** |
| `GET /flights/s/smokeprobe00` short-URL probe | **200** (not 500; OLS→Next) |
| Customer portal `/customer` | **200** |
| Agent portal `/agent` | **200** |
| Admin portal `/admin` | **302** (auth redirect — expected) |
| Ask JetPakistan dedicated path probes (`/ask`, `/ask-jetpakistan`) | **404** (no dedicated public path found; Ask remains product-embedded — not treated as cleanup regression) |
| `GET /api/health` | **404** (known; Laravel `/up` used as health surface) |
| OLS short-URL / route ownership assert | **PASS** (`SHORT_URL_ROUTE_OWNER=NEXT`, `ROUTE_OWNERSHIP_GUARD=PASS`) |
| PM2 both apps | **online**, cwd unchanged |
| Runtime / rollback stamps | unchanged |

## Verdict

```
SERVER_CLEANUP_FUNCTIONAL=PASS
```

No product behavior changes were introduced by cleanup. Active release and BUILD_IDs match the immutable freeze.
