# JP-OPS-READINESS-01 — Deep Live Audit Wave A Baseline

Started after OPS-DEF-0019 CLOSED.

```
WAVE_A_STARTED=YES
STARTED_AT_UTC=2026-10-14T19:32:00Z
RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
DASHBOARD_BUILD_ID=7-iZf4RbaJmGpuDjO1tsh
PENDING_DEFAULT=0
FAILED_JOBS=0
MAIL_NOTIFICATION_QUEUE=notifications-transactional
```

## Active defects carried forward

| ID | Status | Note |
|----|--------|------|
| OPS-DEF-0013 | OPEN | Finance/payment idempotency / DB uniqueness |
| OPS-DEF-0016 | OPEN | Supplier/system activation |
| OPS-DEF-0017 | OPEN | Payment/supplier external activation |
| OPS-DEF-0018 | OPEN | Operational journeys insufficiently live-executed |
| OPS-DEF-0019 | CLOSED | Queue routing proven |
| OPS-DEF-0020 | OPEN | public/storage LEGACY_DIRECTORY\|BLOCKED_BY_EXISTING_FILES — do not symlink blindly |

## A1 infrastructure reconcile (initial)

```
HEALTH_UP=200
PUBLIC_HOME=200
LOGIN=200
AGENT_SURFACE=200
ADMIN_SURFACE=302 (redirect chain; not 500)
DASHBOARD_BUILD_ID_STABLE=YES
FRONTEND_REGRESSION=NO
DEFAULT_WORKER_RUNNING=NO
```

## Priority order (in progress)

A1 runtime baseline — PASS  
A2 role inventory + unauth deep-link negatives — PARTIAL (no QA auth states in workspace)  
A3 public/portal smoke — PASS  
A4 reports/read paths — PARTIAL_UNAUTH_ONLY  
A5 notification observability — PASS_WITH_RESIDUAL_0021  
A6 cron health — PASS  
A7 queue topology — PASS  
A8 failed_jobs/comms — PARTIAL (OPS-DEF-0021)  
A9 CMS read-only — INVENTORIED_NOT_AUDITED (0020 untouched)  
A10 supplier/payment mutation-path source audit — PASS_READ_ONLY (Sabre book live-call ON; ticketing OFF)  
A11 authenticated journey execution — BLOCKED (needs QA sessions)

## Safety

No supplier/payment/wallet/PNR mutations. No historical notification replay. No OPS-DEF-0020 storage:link.
