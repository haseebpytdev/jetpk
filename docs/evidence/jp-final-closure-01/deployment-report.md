# JP-FINAL-CLOSURE-01 — Deployment report (R3 rebuild)

## Deployments

| When (UTC) | SHA | Scope | Backup | Build | Result |
|---|---|---|---|---|---|
| 2026-08-29 earlier | `37d489ce` | groups facet + email lineage | `…T100310Z` | `4KN41ZZvPsqgb3xu8D7Ju` | PASS |
| 2026-08-29T14:00Z | `63e66e65` | `JetpkEmailBrandingResolver.php` only | `jp-final-closure-01-r2-20260829T140045Z` | unchanged | file live; activate client hung after LARAVEL_BOOT — post-activate clear re-run |

## Safety

- `ALHAIDER_BOOKING_ENABLED=false`
- Expected OLS `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`
- No supplier PNR/payment/ticket mutations in R3 harness

## Rollback

- Restore backup `jp-final-closure-01-r2-20260829T140045Z` or prior `…T100310Z`
- Or re-copy `JetpkEmailBrandingResolver.php` from `37d489ce`
