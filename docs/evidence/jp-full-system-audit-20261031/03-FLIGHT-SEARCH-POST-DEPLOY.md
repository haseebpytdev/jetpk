# JP-FLIGHT-SEARCH-LIVE-INCIDENT-01 — Post-deploy certification

DATE: 2026-10-31

## Deploy

| Field | Value |
|---|---|
| File | `app/Services/FlightSearch/FlightSearchService.php` |
| Backup | `/home/pkjetp/backups/jp-flight-search-live-incident-01-20261031-173152/FlightSearchService.php` |
| Pre-hash | c0adc15702862c5646a1711a941645a10889b149fe4738b524c5645d244c5768 |
| Post-hash | 1aa86ce4f84d0c3d749a405d7bbe925a988176516abd7437505ec9409c188d34 |
| Cache | `php artisan optimize:clear` DONE |
| Laravel marker | unchanged `4e6e3acc…` (selective file deploy; marker ≠ parity reminder) |

## LIVE certify (same criteria as pre-fix)

| Check | Result |
|---|---|
| `/laravel/flights/results/search` LHE→KHI 2026-11-20 | **200** (~6.5s) |
| short_url minted | `/flights/s/cfulkpxlizagkicmwm` |
| `/laravel/flights/results/data` | **200** |
| offers | page 12 / total **16** |
| airlines in filters | EK, EY, PK, FZ, GF (preview) |

```text
FLIGHT_READY_FOR_DEPLOY=YES (already deployed)
FIXED=YES
DEPLOYED=YES
LIVE_CERTIFIED=YES
```
