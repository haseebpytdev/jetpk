# OLS — Flight short URL `/flights/s/{ref}` → Public Next

Authority: tracked snippet
`deploy/openlitespeed/jetpakistan-vhost-routes.conf`
(+ extract `docs/jetpk/ols-snippets/flights-short-url.vhconf.snippet`).

Canonical host: `jetpakistan.pk` only.

## Purpose

Browser-visible flight search sessions use opaque Class B refs:

```text
GET/HEAD /flights/s/{code}  →  Public Next (:3010)
```

Laravel remains authoritative for mint/resolve (`/api/public/content/short-refs/{code}`),
results data APIs, and search init. Next SSR resolves the ref via absolute `LARAVEL_URL`
(`publicContentFetchUrl`) and renders the same `FlightResultsPage` **without** redirecting
to `/flights/results?search_id=`.

## Why this rule exists

Exact flight page rules historically covered only:

```text
^/flights/(fare-selection|results|return-options)$
```

Without the short-ref rule, `/flights/s/{code}` fell through to Laravel → soft HTML 404
(Laravel “Page not found”), even when the Next route was built and present.

## Exact rule (canonical)

```apache
RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
RewriteRule ^/flights/s/([A-Za-z0-9]{8,32})$ http://jetpk_public_next/flights/s/$1 [P,L,E=PROXY-HOST:jetpakistan.pk]
```

| Item | Value |
|------|--------|
| Config path | `/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf` |
| Methods | GET, HEAD only |
| Code charset | `[A-Za-z0-9]{8,32}` |
| Handler | `jetpk_public_next` → `127.0.0.1:3010` |
| Host header | `PROXY-HOST:jetpakistan.pk` |
| Ordering | Immediately after exact `/flights/(fare-selection\|results\|return-options)` rule; **before** Laravel catch-all |

## RSC / header expectations

Successful ownership proof: response includes Next App Router signals, e.g.

```text
vary: rsc, next-router-state-tree, …
```

Laravel-owned 404 for the same path typically shows Laravel security headers only
(`x-frame-options`, `cache-control: no-cache, private`) and Blade/theme CSS — **not** RSC vary.

## Apply (deterministic)

1. Backup: `cp -a …/vhconf.conf …/vhconf.conf.bak-shorturl-$(date -u +%Y%m%dT%H%M%SZ)`
2. Ensure the RewriteRule text from the tracked snippet is present (idempotent insert after results rule).
3. `/usr/local/lsws/bin/lswsctrl reload`
4. Run: `bash scripts/jp-ols-assert-flights-short-url.sh`

Historical first-apply backup (already on server):
`/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf.bak-shorturl-20260921T043126Z`

## Rollback

1. Restore the shorturl bak file **or** remove only the `/flights/s/` RewriteCond+RewriteRule pair.
2. `lswsctrl reload`
3. Expect `/flights/s/{code}` to become Laravel 404 again (proves ownership flip).

## Verification commands

```bash
# Live Next ownership (must show RSC vary, HTTP 200)
curl -sI 'https://jetpakistan.pk/flights/s/zzzzzzzzzzzzzzzz' | head -20

# Snippet present on server
grep -n 'flights/s/' /usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf

# Repo ownership unit test
./vendor/bin/phpunit --filter OlsFlightsShortUrlSnippetTest
```

## Gates

```text
OLS_CONFIG_REPRODUCIBLE=PASS   # snippet in git == live rule text
SHORT_URL_ROUTE_OWNER=NEXT     # RSC vary on /flights/s/*
ROUTE_OWNERSHIP_GUARD=PASS     # automated assert script + PHPUnit
```

Also tracked in the same conf file: exact `^/groups$` → Public Next (landing). Live apply backup:
`vhconf.conf.bak-jp-ols-routes-20260921T071621Z`.

## Related

- `docs/closure/SEO-AEO-GEO/10-ols-short-url-rewrite.md` — first live hotfix note
- `docs/jetpk/OLS-GROUP-BOOKING-NEXT-PROXY.md` — same OLS ownership pattern for Groups
- Next page: `frontend/app/flights/s/[ref]/page.tsx`
