# Production-only config audit — `cbd7686f`

**Date:** 2026-09-21

## OLS / OpenLiteSpeed

| Item | Status |
|---|---|
| `/flights/s/{code}` → Public Next | **Tracked** in `deploy/openlitespeed/jetpakistan-vhost-routes.conf`; live matches; assert PASS |
| `/groups` exact → Public Next | Was missing from exact rewrite (Laravel HEAD/cookies, Next body via hub header). **Inserted** from tracked conf 2026-09-21; backup `vhconf.conf.bak-jp-ols-routes-20260921T071621Z` |
| Group booking GETs | Documented in `docs/jetpk/OLS-GROUP-BOOKING-NEXT-PROXY.md` (CORRECTION-08) |
| Secrets in Git | **None** — vhost snippets have no credentials |

## App env (names only)

| Key | Server | Repo |
|---|---|---|
| `LARAVEL_URL` | `http://127.0.0.1:8088` (frontend `.env.production.local`) | documented pattern |
| `NEXT_PUBLIC_LARAVEL_URL` | `https://jetpakistan.pk` | example env |
| `CACHE_STORE` | `file` (single host) | short-ref storage PASS |
| Revalidate secret | server-only | not committed |

## Verdict

```
PROD_CONFIG_AUDIT=PASS
OLS_CONFIG_REPRODUCIBLE=PASS
NO_UNDOCUMENTED_CRITICAL_HOTFIX=PASS
```

Remaining accepted server-only: env secrets, TLS paths, PHP/OLS process topology.
