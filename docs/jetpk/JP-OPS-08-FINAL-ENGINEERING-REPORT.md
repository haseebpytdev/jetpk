# JP-OPS-08 — Final Engineering Report

## Result

**JP_OPS_08=ENGINEERING_PASS_AWAITING_HUMAN_FINAL_UAT**

This is an engineering acceptance result only. It is **not** a declaration of
launch-ready / final business UAT.

## OLS integrity (resolved)

| Field | Value |
|-------|-------|
| Access | Approved root SSH (`root@185.215.166.176`, jetpk key, IdentitiesOnly) |
| Command | `sha256sum /usr/local/lsws/conf/httpd_config.conf` (read-only) |
| Production SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| Expected | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| MATCH | yes |
| OLS files modified | **no** |
| OLS_INTEGRITY | **PASS** |

Earlier `pkjetp` inability to read OLS is noted; root path succeeds and proves
baseline integrity without configuration changes.

## Architecture

- Laravel authoritative domain → durable `users.meta.ops_inbox` → same-origin
  `EVENT_POLLING` dashboard/customer/agent surfaces
- Role/permission-scoped fan-out (support vs finance)
- Support stale concurrency: `lockForUpdate` + `expected_updated_at` → HTTP 409

## Mandatory gates

All required JP-OPS-08 engineering gates are PASS, including:

- Cross-portal routing / support loops / reconnect / duplicate protection
- Stale-state multi-browser concurrency (409 + refreshed closed state)
- Department (role/permission) routing and agent finance fan-out (no money move)
- SOURCE_PARITY (full intended deploy manifest MATCH=yes)
- OLS_INTEGRITY=PASS
- QA_SECURITY_CLEANUP=PASS
- PRIVATE_ORIGIN_EXPOSURE=0

## Evidence pointers

- Scenario matrix: `docs/jetpk/JP-OPS-08-SCENARIO-MATRIX.json` (dimensions clean)
- Task ledger: `docs/jetpk/JP-OPS-08-TASK-STATUS.md`
- Source parity: `docs/jetpk/JP-OPS-08-SOURCE-PARITY.json`
- Progress: `docs/jetpk/JP-OPS-08-PROGRESS.md`
- PHPUnit: `JpOps08*` 15 PASS / 85 assertions (prior reopen tip)
- Playwright: support two-way, stale concurrency, responsive NFR

## Branch

`phase/jetpk-ops-08-cross-portal-realtime`

Human final UAT / launch decision remains a separate step.
