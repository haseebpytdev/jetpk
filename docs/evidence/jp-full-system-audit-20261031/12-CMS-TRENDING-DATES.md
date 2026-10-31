# CMS stale trending depart dates

## Symptom

Homepage Trending Route links exposed `depart=2026-09-22` on 2026-10-31+.

## Root cause

CMS route items pin absolute `cta_url` with past `depart=`.  
`JetpkHomepageSectionData::routeSearchUrl()` previously returned `cta_url` unchanged when set, bypassing dynamic future-date generation.

`homepage_featured_fares` table is empty (0) — not the source.  
Scheduler `jetpk:homepage-route-fares-refresh` updates `_fare_cache` prices, not the pinned CTA URLs.

## Fix

1. Sanitize past `depart=` in pinned `cta_url` → rebuild future search URL.
2. Overwrite public payload `cta_url` with sanitized `search_url`.
3. Unit test: `JetpkHomepageRouteSearchUrlFreshnessTest`.
4. Cleared Next `.next/cache` + PM2 restart (TTL was 3600s).

## Live after fix

- HTML trending links: `depart=2026-11-07`, **pastDates=[]**
- API `search_url`: future
- Customer-facing CTAs: fixed

```
CMS_STALE_FARE_ROOT_CAUSE=CMS_PINNED_CTA_URL_PAST_DEPART
CMS_STALE_FARE_FIXED=YES
```
