# OLS short-search rewrite — production note

**Date:** 2026-09-21  
**Runtime SHA:** `cbd7686feadd35773fd0b597117538b8b99b59fa`  
**Public BUILD:** `kf8S-ybDOI8Vw8LzUye0H`

## Problem

`/flights/s/{ref}` returned Laravel HTML 404 while `/flights/results` returned Next RSC headers.

Root cause: jetpakistan.pk vhost only proxied exact:

```
^/flights/(fare-selection|results|return-options)$
```

## Fix (OLS vhconf)

Backup: `/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf.bak-shorturl-20260921T043126Z`

Inserted:

```
RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
RewriteRule ^/flights/s/([A-Za-z0-9]{8,32})$ http://jetpk_public_next/flights/s/$1 [P,L,E=PROXY-HOST:jetpakistan.pk]
```

`lswsctrl reload` applied.

## SSR companion

Next `/flights/s/[ref]` must resolve short-refs via absolute `LARAVEL_URL` (`publicContentFetchUrl`), not relative `/laravel/*`.

## Probe (same SHA)

| Case | Result |
|---|---|
| Mint + `/flights/s/{ref}` | HTTP 200, title Flight search, results shell, `search_id=` leak = NO |
| Missing ref `zzzz…` | HTTP 200, visible Search expired |
| OLS headers | `vary: rsc…` (Next), not Laravel security-only headers |
