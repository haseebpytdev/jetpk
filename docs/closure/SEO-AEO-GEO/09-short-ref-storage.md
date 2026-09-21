# Short-ref storage durability

**Probed:** 2026-09-21 production  
**CACHE_STORE / CACHE_DEFAULT:** `file`  
**STORE_DRIVER:** `file`

## Assessment

| Check | Result |
|---|---|
| Shared across PHP workers on this host | **YES** — Laravel file cache under `storage/framework/cache` on shared filesystem |
| Survives PHP-FPM / Next process restart | **YES** — disk-backed |
| Survives full app deploy (storage preserved) | **YES** if `storage/` not wiped (standard JetPK deploy) |
| Multi-node / multi-host | **N/A** — single production app host |
| TTL | Matches search session TTL (1800s) via `PublicShortRefService` |

**SHORT_REF_STORAGE=PASS** for current single-host topology.

If production ever becomes multi-node, migrate short refs to Redis/`database` cache or a dedicated table before relying on default short URLs across hosts.
