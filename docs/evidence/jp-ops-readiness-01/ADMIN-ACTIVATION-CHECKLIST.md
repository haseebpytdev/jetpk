# JP-OPS-READINESS-01 — ADMIN-ACTIVATION-CHECKLIST

**Phase:** 17  
**Mode:** READ-ONLY  
**Probed:** 2026-10-14 UTC via SSH + HTTP

Status values: `READY` · `MANUAL_ACTIVATION_REQUIRED` · `EXTERNAL_DEPENDENCY` · `BLOCKED` · `NOT_APPLICABLE` · `TEMPORARILY_BLOCKED`

## A. Infrastructure

| ID | Item | Status | Evidence |
|----|------|--------|----------|
| A-01 | TLS host jetpakistan.pk | READY | HTTP `/` 200 |
| A-02 | `/up` health | READY | 200 |
| A-03 | Runtime SHA marker | READY | `12045dda…` SSH |
| A-04 | Authorized SHA | READY | matches runtime |
| A-05 | Dashboard BUILD_ID | READY | `7-iZf4RbaJmGpuDjO1tsh` |
| A-06 | Public frontend BUILD_ID | READY | `QClrSvr5W0V2GqllIeLKE` (observed; not in original freeze) |
| A-07 | Next listeners 3001/3010 | READY | ss |
| A-08 | Laravel 8088 | READY | ss |
| A-09 | PM2 daemon | READY | pid 405709 |
| A-10 | Disk space | BLOCKED | **89% used** — OPS-DEF-0014 |
| A-11 | Main vs runtime SHA reconcile | BLOCKED | OPS-DEF-0001 |

## B. Runtime config

| ID | Item | Status | Evidence |
|----|------|--------|----------|
| B-01 | APP_ENV=production | READY | SSH probe |
| B-02 | APP_DEBUG=false | READY | SSH probe |
| B-03 | QUEUE=database | READY | SSH probe |
| B-04 | MAIL=smtp | READY | driver set; delivery EXTERNAL_DEPENDENCY |
| B-05 | failed_jobs backlog | BLOCKED | **147** — OPS-DEF-0015 |
| B-06 | Cron schedule:run | READY | crontab every minute |
| B-07 | Notification queue cron | READY | present |

## C. Business activation

| ID | Item | Status |
|----|------|--------|
| C-01 | Platform modules (search/checkout/portals) | MANUAL_ACTIVATION_REQUIRED |
| C-02 | Supplier connections + live flags | MANUAL_ACTIVATION_REQUIRED |
| C-03 | PIA live | BLOCKED (FROZEN) |
| C-04 | AirBlue Zapways live | MANUAL_ACTIVATION_REQUIRED + EXTERNAL_CERTIFICATION |
| C-05 | Sabre live book/ticket | MANUAL_ACTIVATION_REQUIRED |
| C-06 | AbhiPay credentials + callback | MANUAL_ACTIVATION_REQUIRED / EXTERNAL_DEPENDENCY |
| C-07 | OTP/SMS production channel | EXTERNAL_DEPENDENCY |
| C-08 | `config/ota-go-live.php` items | MANUAL_ACTIVATION_REQUIRED (all `done:false` in repo) |
| C-09 | Test booking UAT sign-off | MANUAL_ACTIVATION_REQUIRED |
| C-10 | Rollback doc current SHA | BLOCKED (stale rollback SHA cited historically) |

## Gate

**ADMIN_ACTIVATION_READY = NO** while A-10, A-11, B-05, and supplier/payment activation items remain open.
