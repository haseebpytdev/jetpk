# Deployment report

## Engineering SHAs

1. `e42b904a` — return poll restart + search_id  
2. `17499f44` — share, logo, provider seed, tests  
3. `d25327c6` — Sabre empty-response → search rematch recovery  

## Runtime

AUTHORIZED_SHA=`d25327c69834388de7f2e3672220ed1052245efc`  
PUBLIC_BUILD_ID=`p5fc2fYs9sfh1Tx95NtiQ`  
DASHBOARD_BUILD_ID=`fbzOL_dHxc_Iq0ScPoglD`  

Deploy via `/usr/local/sbin/jetpk-production-run` (explicit checks; no `set -euo`).  
PUBLIC_ONLY Next rebuild as `pkjetp`. Laravel `optimize:clear`. PM2 public restart. OLS/pre-proxy PASS.

## Rollbacks retained

- `jp-flight-shopping-r7c-20260901T064216Z`
- `jp-flight-shopping-r7c-rb2-20260901T064513Z`
- `jp-flight-shopping-r7c-fare-20260901T071136Z`

FINAL_VERIFIED_ROLLBACK_COUNT≥2  
ROOT_DISK≈21% used / 77G free  
SAFE_TO_PUSH=NO
