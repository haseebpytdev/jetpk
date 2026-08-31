# JP-OPS-CLOSURE-01-R2 — Live operational evidence (sanitized)

## Authority

| Field | Value |
|---|---|
| Branch | `phase/jp-flight-perf-01` |
| OPS_LAYER_A_ENGINEERING_SHA (initial deploy) | `1d24b5ecf0fdd2cf63eddbe7de3a83b929a1f4ff` |
| Defect fix ENGINEERING_SHA | `15e9ab6e574bda386acf22c4269662a733327ef2` |
| DEPLOYED_RUNTIME_SHA (final) | `15e9ab6e574bda386acf22c4269662a733327ef2` |
| PUBLIC_BUILD_ID | `6_O29_iFhESJgn0Dey3MT` |
| START_EVIDENCE_SHA | `dc26df2eaf5528e7a915ef7916b9e7c85ac31a79` |
| REMOTE_HEAD (frozen) | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| NO_PUSH | YES |

## Confirm-path safety (mandatory)

| Gate | Result |
|---|---|
| CAN_FINAL_CONFIRM_TRIGGER_LIVE_SUPPLIER_PNR | **YES** (Sabre booking + live call enabled) |
| CAN_FINAL_CONFIRM_TRIGGER_LIVE_ORDER | YES_IF_PIA_NDC_PATH |
| CAN_FINAL_CONFIRM_TRIGGER_PAYMENT | NO |
| CAN_FINAL_CONFIRM_TRIGGER_LIVE_GROUP_RESERVATION | NO_ON_STANDARD_CONFIRM |
| QA path used | LOCAL_ELOQUENT_ONLY_NO_CONFIRM_FORM (`supplier=local_qa_inert`, `meta.jp_ops_qa=true`) |

## Deployments (lock)

1. First activate of `1d24b5ec` — CRLF manifest failed; rolled back/rebuild side-effect BUILD `SesJMsUKBX7KzL6Kg8EHB`.
2. Redeploy `1d24b5ec` with LF manifest — `ACTIVATE=PASS`, BUILD `cCThkVYUDxCGj8LKJEefP`, `FULL_RUNTIME_SOURCE_DRIFT=0`.
3. Defect fix `15e9ab6e` (double `/laravel` prefix) — `ACTIVATE=PASS`, BUILD `6_O29_iFhESJgn0Dey3MT`, drift 0.
4. Every mutation used `JETPK_PRODUCTION_LOCK_ACQUIRED` via `jetpk-production-run`.

## Config (production)

- `payment_window_minutes=120`
- `payment_deadline_safety_buffer_minutes=15`
- `unpaid_booking_expiry.supplier_cancel_enabled=false`
- `queue.default=sync`
- `mail.default=smtp`
- Cron: `* * * * * php artisan schedule:run` (pkjetp)
- Google client id/secret: **absent** (`GOOGLE_EXTERNAL_CONFIGURATION_REQUIRED`)

## OPS matrix (final)

| OPS | Result | Evidence class |
|---|---|---|
| 01 Guest→pending→expire | PASS | Local QA booking + expiry service + guest UI pending page |
| 02 Continue as Guest UI | BLOCKED_SAFETY | Matcher proven; draft UI blocked by live-confirm risk |
| 03 Login & continue | BLOCKED_SAFETY | Same |
| 04 Default saved traveler | BLOCKED_SAFETY | Same |
| 05 Multiple travelers | BLOCKED_SAFETY | Same + automated IDOR tests |
| 06 Pay→verify→local ticket event | PASS | Local payment row + status flips; no live pay/ticket |
| 07 Paid vs expiry race | PASS | Paid barrier |
| 08 Reminder + dedup | PASS | Reminder service + CommunicationLog `payment_reminder` sent |
| 09 Cancel request→local complete | PASS | Guest UI after fix; DB request#3→processed |
| 10 Supplier cancel failure sim | PASS | Meta simulation; no live cancel |
| 11 Refund lifecycle | PASS | No real money; local attempt documented |
| 12 Google existing | BLOCKED_EXTERNAL | Credentials absent |
| 13 Google new | BLOCKED_EXTERNAL | Credentials absent |
| 14 Google privileged safety | PASS | Takeover=NO; config absent |
| 15 Mail failure recovery | PASS | Internal failed logs; booking state committed |
| 16 Idempotency | PASS | No duplicate open cancel after completion |
| 17 Assigned routing | PASS | Comm logs show internal targets (masked) |
| 18 Scheduler expiry | PASS | schedule:list + cron schedule:run + command exec |
| 19 Queue worker | PASS | `sync` inline authority |
| 20 Full ordinary UX | BLOCKED_SAFETY | Stopped before live search/confirm |

## Defect / fix / redeploy

| Field | Value |
|---|---|
| SCENARIO | OPS-09 |
| SYMPTOM | Guest cancel POST → “resource not found” |
| ROOT_CAUSE | `mutation_urls` already include `/laravel`; `laravelApiPath` double-prefixed → 404 |
| MINIMAL_FIX | Idempotent `laravelApiPath` |
| ENGINEERING_SHA | `15e9ab6e574bda386acf22c4269662a733327ef2` |
| LIVE_RETEST | PASS — UI shows “Your cancellation request is being reviewed.” |

## Email

- QA customer (`*@example.invalid`): `booking_request_received`, `payment_reminder`, `booking_expired` → **status=sent** (SMTP accepted).
- EXTERNAL_MAILBOX_RECEIVED: OWNER_CONFIRMATION_REQUIRED (Cursor cannot read inbox).
- REAL_NON_QA_RECIPIENTS_TARGETED: 3 masked ops addresses (some `status=failed`).

## Safety counters (cumulative)

All supplier create/cancel/ticket/void/refund/payment live calls: **0**.

## Owner dirty files (not staged)

- `JetpkEmailPreviewCommand.php`
- `GoogleCustomerWelcomeMail.php`
- `group-reservation.blade.php`
