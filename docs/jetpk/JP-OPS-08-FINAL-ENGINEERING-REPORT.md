# JP-OPS-08 — Final Engineering Report

## Result

**JP_OPS_08=FAIL_NOT_OPERATIONALLY_CLOSED**

Prior ENGINEERING_PASS was retracted because matrices/tasks contradicted the claim.
Implementation was retained (`EVENT_POLLING` unchanged). After reconciliation and
new concurrency/routing work, **all mandatory operational/scenario engineering
gates are green except OLS integrity re-hash**, which remains an external hard
blocker after exhausting the approved `pkjetp` SSH path.

## Remaining hard / external blocker

### OLS_INTEGRITY

Expected global hash:

`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

Probe evidence (2026-08-12, no OLS files modified):

- `/usr/local/lsws/conf/httpd_config.conf` → **exists, unreadable** by `pkjetp`
- `sudo -n sha256sum ...` → **`sudo: a password is required`**
- Alternate candidate paths absent

Therefore:

`OLS_INTEGRITY=EXTERNAL_BLOCKER_UNREADABLE`

Do **not** treat `PASS_NO_MODIFICATION` as a substitute for the required
baseline comparison until a privileged operator can run a read-only hash.

## Architecture (unchanged decision)

- Laravel authoritative domain → durable `users.meta.ops_inbox` → same-origin
  `EVENT_POLLING` dashboard/customer/agent surfaces
- Role/permission-scoped fan-out (support vs finance permissions)
- Support stale concurrency via `lockForUpdate` + optional `expected_updated_at`
  → HTTP **409** with fresh ticket state

## Evidence highlights

| Gate | Evidence |
|------|----------|
| Support loop production | create→assign→reply latencies 2416 / 1466 / 1732 ms |
| Stale concurrency production | Staff close + Admin assign → **409 stale_state**, refreshed status `closed`, assignee null |
| Department routing | PHPUnit: support-only staff unread=0 on deposit; finance-only unread=0 on support create |
| Outward status | PHPUnit: status change fans to customer; assignment/internal do not |
| Agent finance | PHPUnit: balance unchanged; deep_link `agents/deposits`; agency B denied |
| Responsive NFR | widths 768–1920 + zooms 80–125 PASS |
| SOURCE_PARITY | Full intended manifest MATCH=yes (`JP-OPS-08-SOURCE-PARITY.json`) |
| QA cleanup | ids 8–11 suspended; sessions 0; login denial 422×4; OTP required; OTP_DEMO_* PRESENT |
| PRIVATE_ORIGIN_EXPOSURE | 0 |

## Tests

- `php artisan test --filter=JpOps08` → **15 PASS / 85 assertions**
- Playwright: support two-way, stale concurrency, responsive NFR PASS

## Branch

`phase/jetpk-ops-08-cross-portal-realtime` (implementation tip includes `7f0f179`+)

## Human-only next step to unlock ENGINEERING_PASS

1. Privileged read-only OLS hash of `httpd_config.conf` compared to expected baseline
2. If MATCH → regenerate final report to `ENGINEERING_PASS_AWAITING_HUMAN_FINAL_UAT`
3. If drift → HARD STOP investigation (no autonomous OLS edits)

No commercial QA side effects were created for finance/booking ledger fixtures.
