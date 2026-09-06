# JP-MASTER-CLOSURE-09 SUMMARY

## Phase name
JP-MASTER-CLOSURE-09

## Branch name
`phase/jp-master-closure-09`

## Objective
Close remaining Traveler NAV unattributed time with exclusive intervals, keep FRESH supplier pre-wall, and make homepage CMS cards commercially real: dynamic trending/destination fares from cache, featured deals from group inventory, Human Support unicode.

## Exact lineage
- FINAL_BRANCH=`phase/jp-master-closure-09`
- FINAL_LOCAL_HEAD=`1a45e403485b81006b4dc65fdf7b5cd1449a8741`
- FINAL_REMOTE_HEAD=`1a45e403485b81006b4dc65fdf7b5cd1449a8741`
- FINAL_PRODUCTION_RUNTIME_SHA=`1a45e403485b81006b4dc65fdf7b5cd1449a8741`
- FINAL_PUBLIC_BUILD_ID=`5tU8wCFhHmXtk5tSvTDcC`
- FINAL_DASHBOARD_BUILD_ID=`knBdbMBLDH3sxWqzoMDYu` (unchanged)

## NAV_UNATTRIBUTED_ROOT_CAUSE=
`BROWSER_QUEUE_FETCHSTART_TO_REQUESTSTART`

The prior 680ms hole was Navigation Timing `fetchStart`→`requestStart` stall (connection reuse / browser queue), not Traveler JS boot. DNS/TCP/TLS were zero because the connection was reused. Application NAV residual is HTML parse + post-document to shell.

Exclusive buckets now include `QUEUE_BEFORE_REQUEST_MS`. No HARD_ASSIGN change. No fake shell marks.

## FRESH
Supplier prevalidation remains PRE_WALL. FRESH_UNATTRIBUTED=0 after subtracting NAV external and passenger transport from the wall.

FRESH wall P95 and FRESH_APP P95 on the first exact-build N30 (build `5tU8wCFhHmXtk5tSvTDcC`) did **not** meet `FRESH<=2000` / `FRESH_APP<=2000`. Tail samples are dominated by passenger document/network and a slow `/laravel/booking/passengers` origin interval, not a new Book Now sequencing defect. Early passenger GET remains closed.

## Trending fare contract (established)
- TRENDING_TRIP_TYPE=`one_way` (CMS item may set `return`)
- TRENDING_PAX=`1` adult
- TRENDING_CABIN=`economy`
- TRENDING_CURRENCY=`PKR`
- TRENDING_TAX_SEMANTICS=`final_customer_price` minimum among positive offers
- TRENDING_SUPPLIER_ELIGIBILITY=`jetpk_homepage_route_fare` read-only search channel
- TRENDING_TARGET_DATE=`now(Asia/Karachi)+7 days`
- TRENDING_CACHE_TTL=`jetpk_homepage.fare_freshness_hours` (30)
- TRENDING_CHEAPEST_DEFINITION=`min positive final_customer_price`

Homepage request does not shop. Refresh: `jetpk:homepage-route-fares-refresh` (lock, per-route isolation, preserve previous fare on failure).

## Destinations
Origin pool config: `KHI,LHE,ISB`. Background refresh iterates pool; persists `winning_origin`. Click URL `from=` equals displayed origin.

## Featured deals
Source key `group_ticket`. Commercial fields from eligible `group_inventories` (active, seats, future departure, price>0, non-manual_local for guests). CMS may overlay image/headline/badge/inventory_id. Click `/groups/package/{public_id}`. No hold/booking.

## Human Support
Literal `\u2014` decoded in `CmsPlainText`. Production subtitle already contains a real em dash.

## Tests
`php vendor/bin/phpunit tests/Feature/JetpkHomepageContentManagementTest.php` — 11 passed.

## Production browser
Trending dynamic PKR + search prefill date 2026-09-13. Destinations From KHI + matching href. Featured deals group packages. Human Support heading "Stuck mid-booking? Talk to a human."

## Rollback
Restore runtime from `/home/pkjetp/releases/jetpk-20260906T083349Z` / previous SHA `4717eec58d565468934e533abc4ded648d08851b` and public BUILD_ID `4ZjTpl0WNGS3Zn76wl6lz`. No SFTP.

## Final status
CMS/homepage commercial work deployed and verified. Traveler NAV exclusive attribution closed as external browser queue. Traveler FRESH wall/app P95 still above 2000ms on exact-build N30. `MASTER_FINAL_STATUS` is not PASS.
