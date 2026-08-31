# JP-RELEASE-ENHANCEMENTS-01 — Evidence (sanitized)

## Authority

| Field | Value |
|---|---|
| Branch | `phase/jp-flight-perf-01` |
| START_LOCAL_HEAD | `7d70f26451614a0a6936a285eb5ba8f019cec238` |
| ENGINEERING tip / DEPLOYED | `769b76b9699a4175ea241c37eb945a87bad51d10` |
| PUBLIC_BUILD_ID | `QH2riVM0KzAhDn4aCOXCw` |
| DASHBOARD_BUILD_ID | `vusuf0T5POTkwsLY8oUyO` |
| REMOTE_HEAD (frozen) | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| NO_PUSH | YES |

## R3 non-QA email reconciliation (CommunicationLog, 2026-08-31)

Payment-rejected event on booking 20 (R3 gap event):

```
R3_NON_QA_UNIQUE_RECIPIENTS=4
R3_NON_QA_DELIVERY_ATTEMPTS=4
R3_NON_QA_SENT=0
R3_NON_QA_FAILED=4
R3_NON_QA_SKIPPED=0
R3_NON_QA_UNKNOWN=0
R3_NON_QA_DUPLICATES=0
INCORRECT_PRODUCT_ROUTING=NO
QA_RECIPIENT_ISOLATION_GAP=YES (pre-R4)
```

Same-day broader ops fan-out (status-changed / request / cancel / reject):

```
UNIQUE=5
ATTEMPTS=25
SENT=0
FAILED=25
SKIPPED=0
UNKNOWN=0
DUPLICATES=20
RECONCILE_OK=YES
```

Addresses not printed (masked domain roles only).

## R4 QA recipient isolation

Mechanism: `QaOperationalCommunicationGuard` — for `local_qa*` / `meta.jp_ops_qa` / `jp_ops*` channel bookings, keep `@example.invalid` customer mail; redirect ops to `qa-ops-sink@example.invalid`. Not activatable via public request params.

```
QA_RECIPIENT_ISOLATION=PASS
PRODUCTION_RECIPIENT_ROUTING_UNCHANGED=YES
PUBLIC_QA_MAIL_OVERRIDE_AVAILABLE=NO
R4_REAL_NON_QA_EMAILS_TARGETED=0
QA_RECIPIENT_CONTROLLED=YES
```

Controlled retest: in-process filter proof on production PHP (no SMTP send required).

## FAB (JP-UI-FAB-01)

```
FAB_BREAKPOINT=<xl (1280px)
FAB_DESKTOP_ACTIVE=NO
LOGO_OVERLAP=NO (FAB bottom-right; logo top-left)
NAV_OVERLAP=NO (DesktopNavigation xl:flex only)
```

Responsive matrix (geometry): see `fab/responsive-matrix.json` (generated from live widths 1920→360).

## Short links (JP-SHARE-LINK-01)

```
ACTIVE=/f/PVCUD1F5 → 302 to /flights/results?...
EXPIRED=/f/UZMQSE7L → 200 useful expired page
UNKNOWN=/f/ZZZZZZZZ → 404
JETPAKISTAN_REFERENCE_TTL=180 minutes (config)
```

Copy format: no leading dash; short URL via create API; JetPakistan branding line.

## AI (JP-AI-ASSIST)

```
AI_SAME_VPS=YES (architecture under ai-assistant/)
AI_CAPACITY: AMD EPYC, 6 vCPU, ~12GB RAM, ~10GB available, swap 2GB, GPU=NONE
AI_RUNTIME=BLOCKED_CAPACITY (model not installed; enabled=false)
AI_LISTEN_PUBLICLY=NO
AI_HEALTH enabled=false gateway=not_loaded
AI_CHAT fail-closed (CSRF-protected POST; unavailable when enabled)
AI_SUPPLIER_MUTATION_CALLS=0
```

## Commercial safety

All supplier mutation counters remain 0/NO for this run.
