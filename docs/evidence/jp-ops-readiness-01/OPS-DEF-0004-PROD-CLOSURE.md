# OPS-DEF-0004 — Production Closure

```
MERGED_SHA=922f15774ece177d8b3cc23207e98379b8a2755b
PR=#83
PR_HEAD=b1683f7a19bb7b46bb48f3ccde800e24a273c951
PRE_RUNTIME_SHA=cc21dc9261dd8124b0b9744ee2f6c6a0a1c3b7bf
POST_RUNTIME_SHA=922f15774ece177d8b3cc23207e98379b8a2755b
DEPLOY_MODE=SCOPED_FOUR_FILE_PATCH
BUILD_ID=QClrSvr5W0V2GqllIeLKE
OPS_DEF_0004_STATUS=CLOSED
CLOSED_AT_UTC=2026-10-15T19:35:00Z
```

Scoped security patch (not full release-tree extract). Under `app/`, Git delta `cc21dc92…922f1577` is exactly the four deployed PHP files. Remaining delta is docs/tests/frontend docs/summary only.

## FILES_DEPLOYED

| Path | SHA256 (deployed = main 922f1577) |
|------|-----------------------------------|
| `app/Support/Agents/AgentPermission.php` | `f60d629fee28b85017082f342ce2297fe51ff72292c3816e6b2400a6537d0085` |
| `app/Policies/BookingCancellationPolicy.php` | `b40fdacb1631fe34e1d23ffcb7240ea9c04a7e9a5c281949e7acb6a55d46a0c3` |
| `app/Support/Agencies/AgencyRolePermissionMatrix.php` | `d89aa379252cf69efd7afca63ca1f955ef9d5a7af6031a47736ac0e8ccc4c63b` |
| `app/Support/AgentPortal/AgentPortalBookingDetailPresenter.php` | `c7b4cc3a6ef8e60b8170b808a203464656d8d4ded0e44be5f13ef906e72c32e4` |

```
AGENT_PERMISSION_SHA_MATCH=YES
BOOKING_CANCELLATION_POLICY_SHA_MATCH=YES
AGENCY_ROLE_PERMISSION_MATRIX_SHA_MATCH=YES
AGENT_BOOKING_PRESENTER_SHA_MATCH=YES
UNEXPECTED_RUNTIME_FILES=0
FRONTEND_RUNTIME_FILES_CHANGED=0
MIGRATIONS_CHANGED=0
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
FILE_PARITY=PASS
```

## Deployment method

1. Pre-deploy health (SSH + curl + failed_jobs)
2. Scoped backup: `/home/pkjetp/backups/ops-def-0004-scoped-20261015T193139Z/`
3. SCP exact `git archive` bytes from `922f1577`
4. SHA256 parity gate
5. `php -l` all four; `php artisan optimize:clear`
6. Markers → `922f1577…` after Gate/presenter/health PASS

No migrations, npm, frontend/dashboard rebuild, `.env` changes, permission JSON writes, or cancellation POSTs.

## PERMISSION_INVENTORY (read-only, pre-deploy)

```
AGENT_STAFF_TOTAL=7
AGENT_STAFF_WITH_BOOKINGS_VIEW=7
AGENT_STAFF_WITH_CANCELLATIONS_REQUEST=0
AGENT_STAFF_BOOKINGS_VIEW_WITHOUT_CANCEL_PERMISSION=7
ROLE_VIEWER_WITH_CANCEL_PERMISSION=0
ROLE_MANAGER_WITH_CANCEL_PERMISSION=0
ROLE_SUPPORT_WITH_CANCEL_PERMISSION=0
ROLE_SALES_WITH_CANCEL_PERMISSION=0
```

QA fixtures (username markers only): Viewer/Manager/Support/Sales all lack `agent.cancellations.request` pre-deploy (expected).

## Gate / presenter proofs

| Probe | Pre | Post |
|-------|-----|------|
| Viewer booking view (owned in-memory) | n/a (QA booking wrong agent) | ALLOW |
| Viewer cancellation request | DENY on QA booking (agent mismatch); vulnerable path not reproducible with current QA booking | DENY on owned in-memory |
| Viewer `request_cancellation` action | ABSENT (wrong agent) | ABSENT / NO |
| Owner cancellation request | n/a (no DB owned booking) | ALLOW (in-memory owned) |
| Authorized staff with both perms | — | NOT_EXECUTED_NO_EXISTING_PERMISSION_ASSIGNMENT |
| Manager without cancel grant | — | DENY |
| Customer own / other | — | ALLOW / DENY |
| Platform staff (CancellationsCreate) / admin | — | ALLOW / ALLOW |
| Cross-agency / wrong-agent | — | DENY / DENY |
| Admin approve/reject/process | — | ALLOW |
| Owner approve | — | DENY |

```
VIEWER_GATE_PRE=DENY_ON_QA_BOOKING_AGENT_MISMATCH; PRE_DEPLOY_VULNERABLE_PATH_NOT_REPRODUCIBLE_WITH_CURRENT_DATA=YES
VIEWER_GATE_POST=DENY
VIEWER_PRESENTER_POST=ABSENT
OWNER_REGRESSION=PASS
CUSTOMER_REGRESSION=PASS
PLATFORM_STAFF_REGRESSION=PASS
PLATFORM_ADMIN_REGRESSION=PASS
CROSS_AGENCY_PROOF=DENY
WRONG_AGENT_PROOF=DENY
```

## EXISTING_STAFF_PERMISSION_IMPACT

```
EXISTING_MANAGER_NEEDS_EXPLICIT_GRANT=YES
EXISTING_SUPPORT_NEEDS_EXPLICIT_GRANT=YES
EXISTING_SALES_NEEDS_EXPLICIT_GRANT=YES
```

Role templates suggest cancel-request for Manager/Support/Sales but do **not** auto-write `users.meta.agent_permissions`. Operational follow-up: grant via normal permission-management UI only where required. Viewer remains excluded. This does **not** reopen OPS-DEF-0004.

## Health

| Gate | Pre | Post |
|------|-----|------|
| RUNTIME_SHA | cc21dc92… | 922f1577… |
| BUILD_ID | QClrSvr5W0V2GqllIeLKE | QClrSvr5W0V2GqllIeLKE |
| FAILED_JOBS | 1 (QA SMTP bounce id=148) | 1 (same) |
| PENDING_DEFAULT | 0 | 0 |
| DEFAULT_WORKER | NO | NO |
| DISK | 48% | 48% |
| /up / /login /admin /agent | 200/200/200/302/200 | 200/200/200/302/200 |

```
FAILED_JOB_PAYMENT_RELATED=NO
FAILED_JOB_CANCELLATION_RELATED=NO
KNOWN_QA_SMTP_BOUNCE=YES
FRONTEND_REGRESSION=NO
PRODUCTION_HEALTH=PASS
PRODUCTION_CANCELLATION_MUTATIONS=0
PRODUCTION_PERMISSION_MUTATIONS=0
SUPPLIER_CANCEL_EXECUTIONS=0
```

## Closure basis

Deployed code parity + production Viewer Gate DENY (owned in-memory ownership path) + presenter action absent + pre-merge route/DB-negative tests + regression Gate suite. No live cancellation request created.

```
OPS_DEF_0004_STATUS=CLOSED
PERMISSION_ASSIGNMENT_FOLLOWUP=YES_EXPLICIT_GRANTS_FOR_MANAGER_SUPPORT_SALES_AS_NEEDED
```
