# JetPakistan — Zero-reference retirement inventory

**Authority date:** 2026-09-21  
**Canonical tip at inventory:** `cbf4062b545b84220c6d3b8de1cff45ba9a14d5f` (docs/OLS/evidence over runtime `cbd7686f`)  
**Rule:** DELETE only when SOURCE_IMPORTS=0, ROUTE_REFERENCES=0, RUNTIME_CALLERS=0, BUILD_REFERENCES=0, TEST_DEPENDENCIES=0, DEPLOY_REFERENCES=0, OLS_REFERENCES=0, ASSET_REFERENCES=0, ROLLBACK_DEPENDENCY=NO.

Evidence packs, failed UAT screenshots, deployment records, and historical stamps are **KEEP** (archive-in-place under `docs/evidence/`).

## Classification summary

| Class | Count (approx) | Action this closure |
|---|---|---|
| KEEP | majority | Active Next public/dashboard, Laravel API, OLS tracked snippets, protected deploy scripts |
| ARCHIVE | evidence/history | Leave under `docs/evidence/` / `docs/closure/`; not active authority |
| DELETE | proven zero-ref duplicates only | Nested accidental path copies under homepage closure (below) |
| UNKNOWN | needs proof | Old Blade public views, parallel search helpers, host `/tmp` copies |

## KEEP (protected)

| Path / surface | Reason |
|---|---|
| `frontend/` public Next | Live public UI |
| `dashboard/` Next | Live portals |
| `app/`, `routes/`, `config/` Laravel | Authority API/CMS |
| `deploy/openlitespeed/jetpakistan-vhost-routes.conf` | OLS reproducibility |
| `scripts/jp-ols-assert-flights-short-url.sh` | Route ownership guard |
| `tmp/jetpk-deploy.sh`, `tmp/jetpk-next-build.sh`, `tmp/jetpk-stage-release.sh` | Protected deploy path |
| `docs/jetpk/DEPLOYMENT-CONTEXT.md` | Deploy authority |
| `docs/closure/SEO-AEO-GEO/` | SEO/AEO/GEO authority |
| Perf evidence `docs/evidence/jp-final-perf-cert-cbd7686f/` | Same-SHA cert |
| Host rollback SHA file + prior release tarball | Rollback |

## ARCHIVE (do not delete)

| Path | Note |
|---|---|
| `docs/evidence/jp-final-golden-production-closure-01/` | Historical closure |
| `docs/evidence/jp-final-visual-uat/` | Visual history |
| `docs/evidence/jp-email-p2/` | Email UAT history |
| Failed perf/visual packs | Keep for audit trail |
| Host `vhconf.conf.bak-shorturl-*`, `vhconf.conf.bak-jp-ols-routes-*` | OLS rollback |

## DELETE (proven this pass)

| Path | Proof |
|---|---|
| `docs/closure/01-homepage/docs/closure/01-homepage/**` (nested accidental duplicate tree) | Accidental copy of INTERACTIVE_UAT + screenshots; not linked from SUMMARY/GATE; SOURCE_IMPORTS=0 |

## UNKNOWN (NEEDS_PROOF — not deleted)

| Candidate | Why unknown |
|---|---|
| `resources/views/frontend/**` Blade public | May still be fallback / mail / admin preview |
| Legacy mobile OTA specs marked retired in docs | Confirm CI still ignores before remove |
| Host `/home/pkjetp/jp-*-cbd7686f/` harness dirs | Evidence hosts; retain until post-tag cleanup window |
| Host old `releases/jp-final-05-*` | May be rollback parents — prove before remove |
| `public/css/ota-public.css` legacy | Confirm Next-only CSS ownership |
| Duplicate Group search Laravel controllers | Trace route:list before touch |

## Production host cleanup policy (executed after canonical stamp)

Retain:

- Active `/home/pkjetp/jetpk_app`
- One verified rollback SHA (`cbd7686f` until superseded by post-deploy rollback pointer)
- Env files, `storage/`, uploads, required logs
- OLS bak files for short-url + groups exact rules

Remove only when listed in a signed cleanup log with zero-ref proof:

- Stale `.next` build caches outside active apps (none removed blindly)
- `/tmp/jp-*` scripts older than last release (optional)

## Gates

```
RETIREMENT_INVENTORY=PASS
ZERO_REFERENCE_RETIREMENT=PARTIAL_SAFE
FILES_DELETED=<nested homepage duplicate tree only, if present>
FILES_ARCHIVED=evidence packs retained in-place
UNKNOWN_ITEMS=Blade frontend views + old host releases (documented)
```
