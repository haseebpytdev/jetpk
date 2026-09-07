# JP-MASTER-UNFINISHED LEDGER

Phase: `JP-MASTER-UNFINISHED-CLOSURE-10`  
Branch: `phase/jp-master-unfinished-closure-10`  
CODE_SHA: `92fc5a6fd9214c280f9456096d3bcdb62f3257e5` (P1B live closure baseline; P2 engineering SHA supersedes after commit)  
PRODUCTION_RUNTIME_SHA: `92fc5a6fd9214c280f9456096d3bcdb62f3257e5` until P2 protected deploy

Allowed statuses only: `VERIFIED_DONE` | `OPEN` | `MISSED_OPEN` | `BLOCKED_SAFETY` | `OWNER_HOLD` | `INTENTIONAL_DEFER`

## Active master classification

| Gate | Status |
|---|---|
| AUTH_LOGIN_EMAIL_PATH | VERIFIED_DONE (P0 canonical + P1B production certify) |
| P1B_NOTIFICATION_RUNTIME | VERIFIED_DONE on `92fc5a6f` |
| P2_EMAIL_TEMPLATE_CONSISTENCY_DATA_ACCURACY_UI | OPEN → engineering complete pending deploy/UAT |
| BACK_BFCACHE_MATRIX | VERIFIED_DONE |
| OFFER_FRESHNESS_SAFETY | VERIFIED_DONE |
| ACCOUNTING_PAGE_AUDIT | VERIFIED_DONE |
| WALLET_ADJUSTMENT_RBAC | VERIFIED_DONE |
| WALLET_ADJUSTMENT_SAFETY | VERIFIED_DONE |
| ADMIN_COMPANY_PROFILE | VERIFIED_DONE |

## Email roadmap

- P0: auth canonical shell — closed
- P1/P1B: notification outbox/async/identity — closed on production `92fc5a6f`
- P2: existing JetPakistan template consistency / data accuracy / UI — this phase
- Do **not** use wording: template externalization
- Next only if needed: `P3_EMAIL_TECHNICAL_DEBT_ONLY_IF_REQUIRED`

## Auth email

P0 routes JetPK `auth_*` through `JetpkEmailEventRenderer` + `emails.themes.jetpakistan.layouts.base`.  
P2 removes unintended live `emails.layouts.modern` View callers; `ModernEmailLayout` helpers (masking) remain.

See `docs/jetpk/email/P2-FINAL-CLOSURE.md`.


## Back/BFCache

Prior 150s timeout: harness swallowed `/laravel/flights/results/search` errors, omitted `view=pair`/`sort=cheapest`, and used a later date window. Classification **F (harness defect)** plus **B** (criteria). Traveler-known LHE–DXB 2026-09-23/30 with `view=pair` yields cards. SPA Back did not fire `pageshow`/legacy `back_forward`; remount + `popstate` + snapshot clock at checkout-leave now refresh after 5s.

```
BACK_BFCACHE_MATRIX=PASS
BACK_WITHIN_5S_EXTRA_SUPPLIER_SEARCHES=0
BACK_AFTER_5S_FRESH_REFRESH_COUNT=1
BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT=0
```

## Wallet test errors (was 3)

| TEST_NAME | ERROR_CLASS | CLASSIFICATION |
|---|---|---|
| test_manual_credit_appears_in_admin_statement | Error (RedirectResponse::render) | PRE_EXISTING_TEST_DEFECT (parent `4847a781` already redirects HTML show to dashboard) |
| test_manual_debit_appears_in_statements | same | PRE_EXISTING_TEST_DEFECT |
| test_reversal_appears_in_admin_statement | same | PRE_EXISTING_TEST_DEFECT |

`CURRENT_CODE_TOUCHED_BY_JP10C=NO` for `FinanceStatementController`. `REPRODUCES_ON_PARENT_4847A781=YES`. Helper now requests `format=json`. Assertions still require “Manual wallet credit/debit/reversal” in movement descriptions.

```
WALLET_TEST_ERRORS=0
WALLET_TEST_FAILURES=0
WALLET_TESTS_PASSED=56
```

## Count snapshot

- OPEN_COUNT=0
- AUTHENTICATED_ADMIN_UAT_OWNER_HOLD_COUNT=1 (`AUTH_EMAIL_GMAIL_PROOF`)
- MISSED_OPEN_COUNT=0
- OWNER_HOLD_COUNT=MOFA + CHATWOOT + HISTORICAL_GIT_PURGE
- BLOCKED_SAFETY_COUNT=1 (customer ticket artifact)

`MASTER_FINAL_STATUS=BLOCKED_PENDING_ONE_SUCCESSFUL_OWNER_LOGIN`

Carry-forward unchanged: Ask JetPakistan, CMS homepage, Company Profile, Wallet production copy, Traveler decomposition `PASS_WITH_DIRECTLY_MEASURED_EXTERNAL_FLOOR`.

## Notification architecture P1

Additive tables `notification_outbox`, `notification_deliveries`, `notification_routes`. Deployed `f396c2c25db042900230a36b47c7f4381bce4abe` (`MIGRATE_OK`, outbox backlog 0). JetPakistan auth mail remains the canonical shell. OTP/password-reset/verification stay on existing Laravel/Mail paths. Pipeline async defaults off (inline) so no stranded jobs.

See `docs/jetpk/notification-architecture/`.

All commercial mutation zeros remain 0. No passwords/cookies/session IDs logged.
