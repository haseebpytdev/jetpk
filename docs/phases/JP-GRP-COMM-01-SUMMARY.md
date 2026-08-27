# JP-GRP-COMM-01 — Al-Haider seat sync + UAT readiness

## Status

**FINAL_STATUS=FAIL (BLOCKED)** — supplier seat decrement proven; supplier cancel blocked (HTTP 403). **Active QA supplier reservation requires owner manual release via Al-Haider.**

**R2 engineering hardening:** complete on branch (see `JP-GRP-COMM-01-R2-SUMMARY.md`). Do **not** deploy superseded SHA `3062567b`. Deploy only after manual cancel of `60175` + seats restored to 5.

## Branch / SHAs

| Field | Value |
|---|---|
| BRANCH | `phase/jp-grp-commercial-seat-sync-01` |
| START_SHA | `2a0cc253da1a32eb0e2fceeddad62b506286b94d` |
| R2_RUNTIME_SHA | `460cdae0441d0e07c563e636280c0e552481ac92` |
| R2_DOCS_SHA | `c4101c99f5988af03515b305b1cbab3ccb5cb5f8` |
| SUPERSEDED_PAYLOAD_SHA | `3062567bdc45d9ac9a06bac2a850671cc832584d` |
| PUBLIC_BUILD_ID | `cFI8u0BMqusPSE84bxubP` |

## Audit cleanup

| Gate | Result |
|---|---|
| REMOTE_TMP_RAW_LOGS_AUDITED | PASS |
| REMOTE_SECRET_EXPOSURE | 0 |
| REMOTE_RAW_DEPLOY_LOG_CLEANUP | PASS (removed tracked `tmp/jp-combined-01/*` deploy logs) |
| JP-COMBINED-01-R2-FINAL.md | committed |

## Al-Haider contract (verified from official Postman collection)

| Item | Semantics |
|---|---|
| ALHAIDER_CREATE_BOOKING_SEMANTICS | `POST /api/create/booking` with `group_id`, `agency_info`, `booking_details[]` — reservation hold awaiting payment |
| ALHAIDER_CANCEL_SEMANTICS | `PATCH /api/cancel/booking/{id}` — permission-based; **403 observed on live account** |
| ALHAIDER_RESERVATION_REVERSIBLE | **NO (live unproven)** — cancel permission missing |
| ALHAIDER_MULTI_SEAT_REQUEST_SUPPORTED | YES (`adults`/`child`/`infant` + passenger rows) |
| ALHAIDER_TOKEN_GENERATION_CALLS | 0 |
| CREATE_GATE | `ALHAIDER_BOOKING_ENABLED` (create only) |
| CANCEL_GATE | `ALHAIDER_CANCEL_ENABLED` (cancel only; independent) |

## Live seat-sync attempt (owner-authorized)

| Field | Value |
|---|---|
| SUPPLIER_GROUP_ID | 3348 |
| SECTOR | LYP-SHJ |
| TEST_REQUESTED_SEATS | 1 |
| T0_SUPPLIER_AVAILABLE | 5 |
| T1_SUPPLIER_AVAILABLE | 4 |
| SUPPLIER_SEAT_DECREMENT_PROVEN | **PASS** |
| SUPPLIER_RESERVATION_ID | **60175** |
| SUPPLIER_RESERVATION_CANCELLED | **FAIL (HTTP 403)** — awaiting owner manual cancel |
| T2_SUPPLIER_AVAILABLE | not restored |
| SUPPLIER_SEAT_RESTORE_PROVEN | **FAIL** (pending owner) |
| ACTIVE_QA_SUPPLIER_RESERVATIONS | **1** (pending owner) |

## Engineering fix (payload + R2 hardening)

Root cause of initial 422: JetPK sent `seats`/`reference` instead of official `agency_info` + `booking_details`.

R2 supersedes `3062567b` with:

- Official payload builder + **fail-closed** passenger/contact validation (no synthetic production data)
- Supplier/local **release atomicity** (cancel success before local seat release)
- Separated **create/cancel** env gates
- `SupplierReleaseFailed` admin reconciliation panel + manual reconcile
- Regression tests (mocked; no live mutation)

## Local / automated tests (R2)

- `GroupReservationSupplierReleaseAtomicityTest` + `AlHaiderGroupBookingPayloadBuilderTest`: **17/17 PASS** (73 assertions)

## Owner action required (HARD STOP)

1. **Manually cancel Al-Haider supplier booking `60175`** (group 3348, 1 seat) via supplier portal/support — API token lacks cancel permission (`403`).
2. Verify supplier seats return to **5** for group 3348.
3. Authorize protected deploy of R2 engineering SHA with `ALHAIDER_BOOKING_ENABLED=false`.
4. After deploy: reconcile local booking for `supplier_reservation_id=60175` via admin manual-cancel reconcile (no PATCH).
5. Delete disposable QA customer `user_id=14` after UAT.
6. Keep `ALHAIDER_CANCEL_ENABLED=false` until cancel permission/operator policy is approved.

## Gates (unchanged on production .env)

| Gate | Value |
|---|---|
| GROUP_PUBLIC_CHECKOUT_ENABLED | YES |
| GROUP_LOGIN_REQUIRED | YES |
| GROUP_RESERVATION_GATE (`ALHAIDER_BOOKING_ENABLED`) | OFF |
| `ALHAIDER_CANCEL_ENABLED` | OFF |
| GROUP_PAYMENT_GATE | manual payment only |

## Evidence

- `docs/evidence/jp-grp-commercial-seat-sync-01/seat-sync-proof.json`
- `docs/evidence/jp-grp-comm-01-r2/hardening-closeout.json`
- Local probe outputs: `tmp/jp-grp-commercial-seat-sync-01/`
