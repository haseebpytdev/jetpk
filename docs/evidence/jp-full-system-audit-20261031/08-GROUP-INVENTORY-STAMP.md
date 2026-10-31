# Group inventory stamp — 2026-10-31

| Metric | Value |
|---|---|
| GROUP_INVENTORY_TOTAL | 245 |
| GROUP_ACTIVE_FUTURE (depart >= today) | 66 |
| GROUP_EXPIRED | 179 |
| GROUP_BY_SUPPLIER | alhaider=243; manual_local=2; ameer_e_millat=0 |
| GROUP_LAST_SYNC | synced_at max = **2026-10-31 02:00:10** |
| GROUP_LAST_RELEASE | not separately stamped in inventory table |
| GROUP_LAST_SYNC_FAILURE | no matching lines in laravel.log tail this run |
| GROUP_LAST_RELEASE_FAILURE | not observed in log tail |

## Scheduler

| Command | COMMAND_REGISTERED | COMMAND_ACTUALLY_EXECUTED_RECENTLY |
|---|---|---|
| `group-ticketing:sync-inventory` | YES (daily 02:00; schedule:list) | YES — inventory synced_at 2026-10-31 02:00:10 |
| `group-ticketing:release-expired` | YES (every minute) | cron present; log lines not found in sampled laravel.log |
| `jetpk:homepage-route-fares-refresh` | YES (00:30) | registered |
| `homepage:refresh-featured-fares` | YES (05:00) | registered; table `homepage_featured_fares` count=0 |

## Public UX

- `/groups/search` loads; cards present (~5 cardish nodes in smoke)
- Homepage Featured Deals section: CMS `featured_deals` (Air Arabia / AirBlue / AirSial cards) — **not** `homepage_featured_fares` table (empty)

## Classification

`PASS_READ_ONLY` / inventory current stamp obtained. Ameer-e-Millat contributes 0 rows currently.
