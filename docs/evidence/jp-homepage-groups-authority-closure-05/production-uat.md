# Production UAT — Closure-05

**Status:** PASS  
**Date:** 2026-09-09  
**PRODUCTION_ENGINEERING_SHA:** `f039bef3dda1320c08fdccb4633d5c7c34b3b61e`  
**REMOTE_EVIDENCE_POLICY_HEAD:** `bba0967145cf44a9aecd11453fe267b00c439736` (not deployed)

## Deploy confirmation

| Gate | Result |
|---|---|
| PRODUCTION_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| LIVE_HTTP | 200 |
| BACKUP | PASS (`BACKUP_TS=20260908T181924Z`) |
| STAGED_SOURCE_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| FILE_ACTIVATION | PASS |
| PUBLIC_BUILD + DASHBOARD_BUILD | PASS (full `jetpk-next-build.sh`) |
| PRE_PROXY_GATE | PASS |

## Fare provenance (§4)

| Gate | Result | Evidence |
|---|---|---|
| `jetpk:homepage-fare-provenance-audit --profile=jetpk --json` | PASS | `production-fare-provenance-audit.json` |
| TRENDING_TRUE_CHEAPEST | PASS (4/4 routes) | artisan + `trending-cheapest-provenance.json` |
| DESTINATION_TRUE_CHEAPEST | PASS (4/4 destinations, KHI/LHE/ISB pool) | artisan + `destination-cheapest-provenance.json` |

Post-deploy: refreshed homepage route fares (`jetpk:homepage-route-fares-refresh`) before audit because live Sabre minima had moved vs cached display values.

## CMS browser UAT (§5) — production dashboard

| Gate | Result |
|---|---|
| FEATURED_CMS_LIVE_PARITY | PASS (draft→preview API/DOM parity) |
| CARD_INVENTORY==DETAIL_INVENTORY | PASS (`ALH-3335` / inventory `17`) |
| CARD_AIRLINE==DETAIL_AIRLINE | PASS (`FLY JINNAH`) |
| CARD_SECTOR==DETAIL_SECTOR | PASS (`ISB→SHJ`) |
| CARD_PRICE==DETAIL_PRICE | PASS (`PKR 64,000`) |
| FEATURED_DETAIL_CURRENT_NEXT_UI | PASS |
| DETAIL_AVAILABLE | PASS |
| FEATURED_UNAVAILABLE_CARD_COUNT | 0 |
| FEATURED_FALLBACK | PASS (`global_fallback` when no exact Air Arabia ISB-DXB inventory) |
| PUBLIC_GROUP_CURRENT_UI | PASS |
| LEGACY_GROUP_UI_VISIBLE | NO |
| DEEP_LINK_REFRESH | PASS |
| BACK_NAVIGATION | PASS |
| QA_STATE_RESTORED | PASS |

Evidence: `cms-browser-uat.json`, `production-browser-cert.json`, `screenshots/`

## Support CTA (§6)

| Gate | Result |
|---|---|
| UPLOAD_HTTP | 200 |
| ASSET_RECORD | PASS |
| DRAFT_PREVIEW | PASS |
| PUBLISH | N/A (draft-only UAT; live published CTA unchanged) |
| PUBLIC_IMAGE_HTTP_200 | PASS (preview asset) |
| PUBLIC_RENDER | PASS |
| ALT_TEXT | PASS |
| SUPPORT_CTA_FALLBACK | PASS (fixture removed; baseline restored) |

## Closure-04 regression (§7)

Rerun: `docs/evidence/jp-final-cms-branding-closure-04/closure-04-prod-gates.json` (2026-09-08T19:52Z)

| Gate | Result | Evidence |
|---|---|---|
| STALE_FARE_MISREPRESENTATION | 0 | `production-browser-cert.json` |
| ASK_BASELINE | PASS | `closure-04-prod-gates.json` `ASK_20_TURN_PRODUCTION` |
| DUPLICATE_MESSAGE_IDS | 0 | `closure-04-prod-gates.json` |
| UNEXPECTED_429 | 0 | `closure-04-prod-gates.json` |
| FAB_COLLISION | 0 | `closure-04-prod-gates.json` `FAB_COLLISION_MOBILE_390` |
| BRANDING | PASS | `closure-04-prod-gates.json` `BRANDING_STORAGE_LOGO` |
| FAVICON | PASS | `closure-04-prod-gates.json` `FAVICON_METADATA_AND_HTTP` |
| CMS_2250KB_UPLOAD | PASS | `closure-04-prod-gates.json` `CMS_2250KB_DRAFT_UPLOAD` (Closure-04 gate; Closure-05 uses 48KB fixture for support CTA smoke) |
| TRENDING_CTA_GENERATION | PASS | provenance JSON + Closure-04 `TRENDING_ROUTE_CTA_CONSISTENCY` |

## Operational remediation (not redeploy)

Root-owned files under `storage/framework/cache/data` (from deploy tooling) caused `Permission denied` on flight search cache writes. Remediated with `chown -R pkjetp:pkjetp` on cache/views/bootstrap/cache. No PHP/LiteSpeed config change.

**STATUS=PASS**
