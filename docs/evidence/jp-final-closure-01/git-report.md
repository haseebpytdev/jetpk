# JP-FINAL-CLOSURE-01 — Git report (R6)

## Authority

| Role | SHA |
|---|---|
| Start remote head (R5 evidence) | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| Start runtime (R5C) | `0221a3f9ff26621289eb3ad61b43e3af00b3ebb3` |
| Final R6 engineering | `a603211f0b5cebf73c1770532cfed649030b7a1f` |
| Deployed runtime | `a603211f0b5cebf73c1770532cfed649030b7a1f` |
| Public build | `38WrCuLnbbv8LChWWw4_M` |

Branch: `phase/jp-flight-perf-01`

## Engineering commits (R6)

| SHA | Summary |
|---|---|
| `9d76e579` | Checkout route group; passengers Server-Timing headers; freshness tests |
| `94db66f3` | Checkout layout SSR-anonymous (no getPublicSession await) |
| `691d9c61` | Hard-navigate Book Now → Traveler |
| `6b567d44` | Wall-clock timing rebase across hard-nav; snapshot after T7 |
| `a603211f` | Restore timing on Traveler page mount |

## Intentionally not restored

- R5B `Promise.race` public-layout timeout (`cf03d5cc` regressor)

## Staging safety (engineering commits)

- Exact-path `git add` only
- `SERVER_GOVERNANCE_RULES_STAGED=0`
- No server-governance / private lock docs committed
