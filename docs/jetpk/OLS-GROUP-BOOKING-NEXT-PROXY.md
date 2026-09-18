# CORRECTION-08 — OLS Group booking GET → Public Next proxy

Authority: production OpenLiteSpeed vhost config (external to Git runtime tree).
Canonical host: `jetpakistan.pk` only.

## Purpose

Authenticated Group booking **GET/HEAD** page shells are owned by **Public Next**.
Laravel remains authoritative for booking authorization, hold ownership, payment
submission (POST), release/expiry, and supplier operations via same-origin
`/laravel/*` APIs and form actions.

## Architecture after cutover

```text
Browser
  → OLS (jetpakistan.pk)
  → GET/HEAD /groups/booking/{ref}/(passengers|review|payment|confirmation)
       → Public Next (:3010)  [page UI]
  → POST/mutations + /laravel/* JSON
       → Laravel (:8088)      [domain/business/DB]
```

Passengers rewrite uses `/groups/{ref}/passengers` (historical Laravel path shape).

## Config file

| Item | Value |
|------|--------|
| Config path | `/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf` |
| Backup (CORRECTION-08 apply) | `/root/backups/vhconf.jetpakistan.pk.c08-group-20260918T171013Z.conf` (confirm latest `c08-group-*` on server) |
| Reload | `/usr/local/lsws/bin/lswsctrl reload` |

## Previous value (excerpt)

```apache
    # Authenticated group passenger/payment/review pages deliberately
    # remain Laravel until portal/auth Wave 2.
```

(No GET rewrite; Laravel Blade theme `themes/frontend/jetpakistan` rendered.)

## New value (excerpt)

```apache
    # CORRECTION-08: authenticated group booking UI cut over to public Next.
    # POST mutations remain Laravel (no RewriteCond GET/HEAD match).
    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteRule ^/groups/booking/([^/]+)/payment$ http://jetpk_public_next/groups/booking/$1/payment [P,L,E=PROXY-HOST:jetpakistan.pk]

    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteRule ^/groups/booking/([^/]+)/review$ http://jetpk_public_next/groups/booking/$1/review [P,L,E=PROXY-HOST:jetpakistan.pk]

    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteRule ^/groups/booking/([^/]+)/confirmation$ http://jetpk_public_next/groups/booking/$1/confirmation [P,L,E=PROXY-HOST:jetpakistan.pk]

    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteRule ^/groups/([^/]+)/passengers$ http://jetpk_public_next/groups/$1/passengers [P,L,E=PROXY-HOST:jetpakistan.pk]
```

## Routes affected

| Browser path | Method | Owner |
|--------------|--------|-------|
| `/groups/booking/{ref}/payment` | GET/HEAD | Public Next |
| `/groups/booking/{ref}/review` | GET/HEAD | Public Next |
| `/groups/booking/{ref}/confirmation` | GET/HEAD | Public Next |
| `/groups/{inventory}/passengers` | GET/HEAD | Public Next |
| `/groups/booking/{ref}/passengers` | GET/HEAD | **Not a Laravel route** (404); canonical passengers URL is `/groups/{inventory}/passengers` |
| Same paths | POST / other | Laravel (unchanged) |
| `/laravel/groups/...` APIs | * | Laravel |

## Rollback

1. Restore backup: `cp -a /root/backups/vhconf.jetpakistan.pk.c08-group-<TS>.conf /usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf`
2. Or replace the CORRECTION-08 rewrite block with the previous “remain Laravel until portal/auth Wave 2” comment (no GET rewrites).
3. `/usr/local/lsws/bin/lswsctrl reload`
4. Prove: authenticated GET payment HTML contains Blade asset `themes/frontend/jetpakistan` and **not** `/_next/static` (pre-cutover behavior).

## Safety

- No secrets in this runbook.
- Do not proxy POST payment/submit through Next.
- Do not use forbidden hosts from `docs/jetpk/DEPLOYMENT-CONTEXT.md`.

## Gates

```text
GROUP_ROUTE_PROXY_REPRODUCIBLE=YES
GROUP_ROUTE_PROXY_ROLLBACK_DOCUMENTED=YES
```
