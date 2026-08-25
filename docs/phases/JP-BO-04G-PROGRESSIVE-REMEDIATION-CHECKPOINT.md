# JP-BO-04G Progressive Remediation — Checkpoint

**Status:** PAUSED for resume  
**Checkpoint UTC:** 2026-08-25T14:00:00Z (approx)  
**Branch:** `phase/jp-bo-04g-progressive`  
**Do not treat this docs commit as production runtime pin.**

---

## Authoritative pins (unchanged)

| Pin | Value |
| --- | --- |
| PREVIOUS_LIVE_SHA (rollback) | `7459e76b2b52f95912d3cf38d3b2e747745a456f` |
| DEPLOYED_ENGINEERING_SHA | `b08f4ba088ee1483bf76e6a61277f4946c25c478` |
| REMOTE BRANCH HEAD (pre-docs) | `b08f4ba088ee1483bf76e6a61277f4946c25c478` |
| AHEAD_BEHIND (pre-docs commit) | `0/0` |
| TRACKED_WORKTREE (pre-docs) | CLEAN |

Runtime production remains pinned to engineering SHA **`b08f4ba…`**. Any docs/checkpoint commit after this must not be used as a deploy pin.

---

## What is DONE

### 1) Protected production deploy — PASS

Deployed exact **17** runtime files from Git object `b08f4ba…` (immutable blob/SHA256 manifest recorded locally).

| Area | Count | Result |
| --- | --- | --- |
| Laravel | 6 | activated |
| Config | 1 (`config/ota.php`) | config cache rebuilt |
| Public frontend | 10 | rebuilt + PM2 restart |
| Migrations | 0 | none run |
| Dashboard rebuild | skipped | PID unchanged (`489779`) |

Key deploy results:

```text
BACKUP_ID=jp-bo-04g-progressive-20260825T112700Z
BACKUP=PASS
BACKUP_INTEGRITY=PASS
ROLLBACK_SHA=7459e76b2b52f95912d3cf38d3b2e747745a456f
OLD_PUBLIC_BUILD=Kcx0WsAt3al_ZoNv2P-W_
NEW_PUBLIC_BUILD=VEDrm82AVe7W8h1ND_2OR
LIVE_SOURCE_DRIFT=0
LIVE_DEPLOYABLE_FILE_COUNT=17
OLS_HASH=PASS
OWNERSHIP_DRIFT=0 (frontend .next/node_modules post-build)
PUBLIC_PM2=online
DASHBOARD_PM2=online
PROGRESSIVE_FLAG_ENV_OVERRIDE=FALSE
PROGRESSIVE_SEARCH_EFFECTIVE=TRUE
ACTIVATE=PASS
```

First deploy attempt failed **before mutation** (`lsphp -l` segfault). Fixed by using LiteSpeed CLI `php` for syntax/artisan. Second attempt activated successfully. No emergency rollback required.

### 2) Preflight + supplier classification — PASS

```text
PRODUCTION_PREFLIGHT=PASS
LIVE_ELIGIBLE_PUBLIC_SUPPLIER_CONNECTIONS=2
LIVE_PUBLIC_SUPPLIER_PROVIDERS=sabre,pia_ndc
SUPPLIER_FANOUT_MODEL=SEQUENTIAL
FIRST_RESPONDER_ACROSS_MULTIPLE_CONNECTIONS=NO
MULTI_PROVIDER_FIRST_RESPONDER_LIMITATION=YES
GUEST_BOOKING_ENABLED=YES
CARD_PAYMENT_ENABLED=YES
```

### 3) Back-office Stage-B live proof — PASS

```text
BACK_OFFICE_LIVE_PROOF=PASS
LIVE_CONNECTION_CRUD=PASS
LIVE_CONNECTION_EXTERNAL_CALLS=0
LIVE_CONNECTION_RESIDUE=0
LAST_SABRE_BOOKING_READ_ONLY_BASELINE=PASS
LAST_SABRE_BOOKING_MUTATED=NO
LIVE_MULTI_SABRE_UI=PASS
LIVE_SABRE_GDS_NDC_UI=PASS
LIVE_ABHIPAY_CONFIG_UX=PASS
LIVE_SMTP_UI=PASS
LIVE_RBAC_UI=PASS
CURRENT_CMS_LIVE_REGRESSION=PASS
REPORTS_LIVE=PASS
AUDIT_LIVE=PASS
SYSTEM_HEALTH_LIVE=PASS
LIVE_LEDGER_TEST=NOT_RUN_NO_SAFE_QA_ACCOUNT
NO_REAL_PNR_CREATED_BY_QA=YES
NO_REAL_TICKET_ISSUED=YES
NO_ABHIPAY_PAYMENT=YES
COMMERCIAL_EXTERNAL_SIDE_EFFECTS=0
```

Synthetic inactive connection `JPQA-BO04-20260825T123844Z` created → reload → mask keys → edit → delete → residue 0. No Test Connection / no supplier call.

### 4) Progressive UX — partially proven on live

Observed on `https://jetpakistan.pk` with real supplier search (not fixtures):

| Observation | Evidence |
| --- | --- |
| Status sequence | `searching → partial → ready` (run 1) and `searching → ready` (later samples) |
| First results | LIVE_ONE_WAY_RESULTS_COUNT=12 |
| Loading / checking-more | Observed on at least one run (`loading_mask_visible=true`, `compact_checking=true`) |
| SEARCH_TO_SHELL_MS | **504ms** (best sample), **1078ms** (later sample) — target &lt;1000 |
| PARTIAL_SNAPSHOT_TO_VISIBLE_MS | **1–433ms** — hard gate &lt;1000 **PASS** |
| Revalidation UI | Details CTA shows “Confirming fare…” then proceeds to passengers |
| PUBLIC_5XX (app API, filtered) | 0 on successful focused runs |

**Important:** Do not classify Sabre supplier latency (~7–25s end-to-end) as app delay. App post-supplier paint targets are meeting.

### 5) Protected scripts created (local `tmp/`)

These are the established Windows→SSH wrappers used for this remediation (still under `tmp/`, typically untracked):

- `tmp/jetpk-run-jp-bo-04g-progressive-preflight.sh`
- `tmp/jetpk-run-jp-bo-04g-progressive-backup.sh`
- `tmp/jetpk-run-jp-bo-04g-progressive-deploy.sh`
- `tmp/jetpk-run-jp-bo-04g-progressive-bo-proof.sh`
- `tmp/jetpk-stage-jp-bo-04g-progressive-release.sh`
- `tmp/jetpk-jp-bo-04g-progressive-*.sh` (remote helpers)
- `tmp/jp-bo-04g-progressive/runtime-manifest.txt` + `immutable-manifest.tsv`
- Live harnesses: `tmp/jp-bo-04g-progressive-live-proof.cjs`, `tmp/jp-bo-04g-progressive-live-focused.cjs`
- Evidence JSON: `tmp/jp-bo-04g-progressive/live-proof/live-proof.json`, `live-proof-focused.json`
- Deploy log: `tmp/jp-bo-04g-progressive/deploy-out2.txt`

---

## What is LEFT (resume here)

### A) Live commerce checkout certification — INCOMPLETE / BLOCKED

Full OW → Details → Passenger → **Review** matrix not closed.

Latest blockers while certifying (not Tier-3):

1. **Passenger form incomplete in harness** — international itinerary requires title, nationality, passport number, issuing country, expiry, issue date. Harness was updated to fill these; last interrupted run did not finish a clean Review reach.
2. **Order summary price banner** — at least one passenger sample still showed `order-summary-price-refresh` (`price_needs_refresh_visible=true`) even when body text scrape missed “Price needs to be refreshed”. Must re-verify after successful revalidation:
   - `AUTHORITATIVE_AFTER_REVALIDATION`
   - `PRICE_NEEDS_REFRESH_AFTER_SUCCESS=false`
   - `ORIGINAL_PRICE_REFRESH_BLOCKER=FIXED_ON_LIVE_PRODUCTION`
3. **Return Pair + Return Split** — not fully certified to Review in this session (trip-type UI flake + early fatal stops). Split return loading mask / independent brand / review still required.
4. **Direct-only partial parity** — not fully completed as a dedicated timed sample.
5. **Performance P95 aggregation** — individual samples exist; formal MIN/MEDIAN/P95/MAX tables across OW/Pair/Split not finalized.
6. **Screenshots / sanitized log correlation** — many shots intended under `tmp/jp-bo-04g-progressive/live-proof/screenshots/`; packaging into final live-proof doc incomplete.
7. **Final report fields** — `COMMERCE_PRODUCTION_CERTIFICATION` remains **BLOCKED** until Review parity + price-refresh closure. `TIER3_READY` must stay **NO**.

### B) Docs still required after live pass

Create (after live certification succeeds):

`docs/phases/JP-BO-04G-PROGRESSIVE-REMEDIATION-LIVE-PROOF.md`

Push docs separately from runtime pin `b08f4ba…`.

### C) Hard stop still in force

Do **not**:

- create/cancel Sabre PNR
- ticket / void
- AbhiPay payment
- refund settle

Wait for ChatGPT/owner Tier-3 authorization.

---

## Resume checklist (next agent)

1. Confirm live still on `b08f4ba…` / build `VEDrm82AVe7W8h1ND_2OR` / `PROGRESSIVE_SEARCH_EFFECTIVE=TRUE` (read-only).
2. Re-run focused harness:  
   `node tmp/jp-bo-04g-progressive-live-focused.cjs`  
   (passport fields + revalidation wait already patched; fix trip-type Round Trip selector if needed).
3. Prove OW / Pair / Split to **Booking Review**, stop before submit.
4. Prove price banner cleared after successful revalidation (`order-summary-total` visible, refresh banner gone).
5. Capture screenshots + sanitize search IDs from Laravel logs.
6. Write `JP-BO-04G-PROGRESSIVE-REMEDIATION-LIVE-PROOF.md` and final section-41 report.
7. Do not redeploy unless source drift or health failure requires rollback to `7459e76…`.

---

## Interim certification verdict

```text
COMMERCE_ENGINEERING=PASS
PROTECTED_DEPLOY=PASS
PROGRESSIVE_SEARCH_EFFECTIVE=TRUE
BACK_OFFICE_LIVE_PROOF=PASS
LIVE_CONNECTION_CRUD=PASS
COMMERCE_PRODUCTION_CERTIFICATION=BLOCKED
TIER3_READY=NO
OWNER_RETEST_V3_STATE=BLOCKED_PENDING_FINAL_SABRE_LIFECYCLE_PROOF
NEXT=Resume live OW/Pair/Split Review certification + price-refresh closure; then write LIVE-PROOF docs
```

---

## Rollback (if needed)

```text
BACKUP_ID=jp-bo-04g-progressive-20260825T112700Z
ROLLBACK_SHA=7459e76b2b52f95912d3cf38d3b2e747745a456f
Helper: tmp/jetpk-jp-bo-04g-progressive-rollback.sh
```

Only roll back for source/health/checkout breakage — **not** for slow Sabre or missing multi-partial batches.
