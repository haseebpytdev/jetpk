# JP-LARAVEL-PERF-01 — Root causes (measured)

## Before (authority + fresh samples)

| Source | P50 | P95 | Definition |
|---|---|---|---|
| JP-NEXT-PERF-02D aligned N20 | 860 | 4421 | Browser wall of search-init during full page load |
| JP-LARAVEL-PERF-01 direct API N20 (before deploy) | 301 | 340 | `GET /laravel/flights/results/search` API wall |
| Intermittent cold/queue probe | — | ≥11702 | Single PowerShell request under OLS pressure |

## Ranking

| Rank | Cause | P95 contribution (approx) | Notes |
|---|---|---|---|
| ROOT_CAUSE_1 | **Misattribution / connection & OLS queue pressure** treated as Laravel prep | ~3000–4000 ms of 02D P95 vs ~340 ms direct API | First-request timeout 60s observed; warm API ~300 ms |
| ROOT_CAUSE_2 | **Sequential supplier dispatch** | Provider start spread = sum of prior provider walls | Code `foreach` — not ambiguous single SUPPLIER_START |
| ROOT_CAUSE_3 | **MarkupRule full-table load per offer** | Post-supplier DB N×offers | Fixed with per-request memo |
| ROOT_CAUSE_4 | **Duplicate eligibility + airport lookups on init** | Tens of ms warm; higher when DB contended | Skip map once; airport reference memo/TTL |

## Not Laravel CPU

Token refresh and supplier HTTP inside `adapter->search` are supplier/network time.

## Application-controlled target

Warm direct-API init wall and server `INIT_RESPONSE_MS` already ≪ 1000 ms. True `TOTAL_PRE_SUPPLIER_MS` (T0→first network) measured post-deploy via `search_perf`.
