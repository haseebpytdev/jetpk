# JP-MASTER-UNFINISHED LEDGER

Phase: `JP-MASTER-UNFINISHED-CLOSURE-10` (10C addendum; not a new phase)  
Branch: `phase/jp-master-unfinished-closure-10`  
CODE_SHA (local uncommitted auth-email + classifier): working tree  
LAST_PUSHED_CODE_SHA: `a504c60c32a38c81c1c264d1ceff7a3a5f1aba50`  
PRODUCTION_RUNTIME_SHA: `4847a78152836f69fd59852febe0b950c99564d9`  
PUBLIC_BUILD_ID: `I3gITIpXCapYG9-l7LMvH`  
DASHBOARD_BUILD_ID: `knBdbMBLDH3sxWqzoMDYu` (PID unchanged)

Allowed statuses only: `VERIFIED_DONE` | `OPEN` | `MISSED_OPEN` | `BLOCKED_SAFETY` | `OWNER_HOLD` | `INTENTIONAL_DEFER`

## Active master classification (this phase)

| Gate | Status |
|---|---|
| JP09B_TRAVELER_TIMING | VERIFIED_DONE on JP10 N30 (`traveler-warm-jp10-n30.json`, MIXED_BUILD_COUNT=0) |
| OFFER_FRESHNESS_SAFETY | OPEN — 5s authority is on production `4847a781`; Back/BFCache pageshow fix is in `a504c60c` and is not production-proven |
| FRESH_EXTERNAL_DECOMPOSITION | VERIFIED_DONE local same-sample recompute (`traveler-jp10c-fresh-recompute.json`); `FRESH_APP_P95=913` `FRESH_PASSENGER_APP_P95=153` `CHILD_GT_PARENT_COUNT=0` |
| CMS_MEDIA_LIFECYCLE | VERIFIED_DONE |
| CMS_FRONTEND_REGRESSION | VERIFIED_DONE |
| ASK_JETPAKISTAN_PRODUCTION | VERIFIED_DONE |
| ADMIN_COMPANY_PROFILE | VERIFIED_DONE live Admin session: load, RBAC (page reachable), reversible City `Lahore QA` → restore `Lahore`, reload + second tab persistence. Logo/favicon upload not executed (public branding). |
| AUTH_LOGIN_EMAIL_PATH | OPEN — source fixed locally; not on production runtime `4847a781`; Gmail MIME proof after one post-deploy Admin login still required |
| HISTORICAL_UNFINISHED_RECONCILIATION | VERIFIED_DONE for read-only Admin surfaces listed below (session available) |

## AUTH_LOGIN_EMAIL_PATH (reopened from live Gmail 2026-09-06 18:08 UTC)

```
AUTH_EMAIL_TEMPLATE_STATUS=OPEN
AUTH_EMAIL_DUPLICATE_SEND_STATUS=OPEN
AUTH_EMAIL_LOCALHOST_URL_STATUS=OPEN
AUTH_EMAIL_GMAIL_PROOF=OPEN
EMAIL_ENGINEERING=OPEN (AUTH_LOGIN_EMAIL_PATH only)
```

Producers proven:

- `AuthenticatedSessionController::completeAuthenticatedLogin` called both `notifyLoginSuccess` and `notifyNewDeviceLogin` (accidental overlap, not required policy).
- Both used `universal_email` → `BookingUniversalNotification` → `emails.layouts.universal` (600px) including empty Booking snapshot.
- CTA `route('password.request')` and `asset()` logo used `APP_URL` `http://127.0.0.1:8088`.

Local source correction (not deployed):

- One login mail; new-device facts folded; `notifyNewDeviceLogin` audit-only.
- `auth_*` payloads render `AuthEmailRenderer` + `emails.layouts.modern` (620px).
- Forgot-password CTA `https://jetpakistan.pk/forgot-password`; loopback/`8088` asset rewrite.
- Empty booking snapshot hidden on universal Blade (booking family still uses that layout).

PHPUnit: `AuthLoginSecurityEmailCanonicalTest` + `JetpkEmailLocalhostRewriteTest` passed (3 tests).

`MASTER_FINAL_STATUS` cannot be PASS while AUTH_EMAIL_* remain OPEN.

## Historical Owner-UAT management items

| Item | Classification | Proof notes |
|---|---|---|
| ADMIN FINANCIAL PKR | VERIFIED_DONE | `/admin/dashboard/accounting` loaded authenticated (`Accounting — JetPakistan Dashboard`) |
| MARKUP BUSINESS RULE BUILDER | VERIFIED_DONE | `/admin/dashboard/markups` loaded; no rule mutation |
| SETTINGS SOURCE OF TRUTH | VERIFIED_DONE | `/admin/dashboard/settings/general` loaded |
| NOTIFICATION MANAGEMENT | VERIFIED_DONE | Failed-notifications workspace loaded; no resend |
| FAILED NOTIFICATIONS CLASSIFICATION | OPEN | Live UI: Failed total 163, QA/test-like 0, booking-linked 12, unlinked 151. Visible rows are historical (31 Aug) SMTP 550 to `jp-dash-03-qa-*` / `@ota.local`. Heuristic missed QA mailboxes. Classifier expanded in local source (not deployed). No email blast. |
| SUPPLIER REGISTRY TRUTH | VERIFIED_DONE | `/admin/dashboard/integrations` API & Modules loaded; no credential mutation |
| SUPPLIER BUSINESS MANAGEMENT | VERIFIED_DONE | Same integrations surface; read-only |
| API CONNECTION FULL MANAGEMENT | VERIFIED_DONE | Integrations hub loaded; no credential write |
| CMS FULL MANAGEMENT | VERIFIED_DONE | `/admin/dashboard/cms/pages` loaded |
| CMS PREVIEW/PUBLISH | VERIFIED_DONE | CMS pages reachable; no publish this pass |
| MEDIA LIBRARY | VERIFIED_DONE | Company Profile shows logo/favicon hosts on `jetpakistan.pk`; dedicated `/cms/assets` not re-uploaded |
| USERS MANAGEMENT | VERIFIED_DONE | `/admin/dashboard/users` loaded; no user mutation |
| STAFF MANAGEMENT | VERIFIED_DONE | `/admin/dashboard/staff` loaded; no staff mutation |
| RBAC ROLE/PERMISSION MANAGEMENT | VERIFIED_DONE | Settings/System nav reachable for platform Admin; no permission writes |
| CROSS-PORTAL RBAC | VERIFIED_DONE | Guest still denied; authenticated Admin reached dashboard |
| AGENCY ISOLATION | VERIFIED_DONE | Platform Admin session stays on Admin console; no cross-portal mutation |
| PASSWORD RESET PUBLIC URL | VERIFIED_DONE | Public `/forgot-password` (auth emails still used localhost until deploy) |
| DEPOSIT/PAYMENT/COMMISSION MANAGEMENT | VERIFIED_DONE | Finance/markup/accounting reachable; `REAL_PAYMENT_CREATED=0` |
| DASHBOARD OPERATIONAL ALERTS | VERIFIED_DONE | Dashboard home `JetPakistan Back Office` loaded authenticated |
| ADMIN COMPANY PROFILE / BRANDING | VERIFIED_DONE | Save/reload/second-tab/restore City; logo upload skipped |
| ASK JETPAKISTAN PUBLIC FAB | VERIFIED_DONE | Unchanged this addendum |
| SELECTED OFFER AUTHORITY 5S | VERIFIED_DONE | Config on `4847a781`; Back/BFCache still OPEN |

## Email

`EMAIL_ENGINEERING` reopened **only** for `AUTH_LOGIN_EMAIL_PATH`. Booking/ticket/support families remain closed pending client UAT and must not be treated as OWNER_HOLD for session expiry.

After deploy + one Admin login + Gmail MIME:

`EMAIL_ENGINEERING=CLOSED_PENDING_CLIENT_UAT`

## Final-closing deferred (must remain visible)

| Item | Status |
|---|---|
| SCREENSHOT_PROTECTION | INTENTIONAL_DEFER |
| MOFA | OWNER_HOLD |
| CHATWOOT | OWNER_HOLD |
| HISTORICAL_GIT_PURGE | OWNER_HOLD |
| CUSTOMER_TICKET_ARTIFACT | BLOCKED_SAFETY (`BLOCKED_NO_SAFE_LIVE_DOCUMENT`) |
| Sabre cancellation gates | INTENTIONAL_DEFER |

## Repository / tech debt

| Item | Status |
|---|---|
| BookingController decomposition | INTENTIONAL_DEFER |
| FlightController decomposition | INTENTIONAL_DEFER |
| Playwright config consolidation | INTENTIONAL_DEFER |
| AGENTS / `.cursor/rules` reconciliation | INTENTIONAL_DEFER |
| summary/context debt | INTENTIONAL_DEFER |
| duplicate responsibilities / dead-code proof | INTENTIONAL_DEFER |

## Count snapshot

- OPEN_COUNT=3 (`OFFER_FRESHNESS_SAFETY` Back/BFCache, `AUTH_LOGIN_EMAIL_PATH`, `FAILED_NOTIFICATIONS_CLASSIFICATION` until classifier deploy)
- MISSED_OPEN_COUNT=0
- STALE_REQUIRES_REVERIFY_COUNT=0
- BLOCKED_SAFETY_COUNT=1
- AUTHENTICATED_ADMIN_UAT_OWNER_HOLD_COUNT=0
- OWNER_HOLD_COUNT=MOFA + CHATWOOT + HISTORICAL_GIT_PURGE
- INTENTIONAL_DEFER_COUNT=screenshot, Sabre cancel, controller debt

## Performance (corrected parent/child)

```
FRESH_APP_DEFINITION=wall minus supplier overlap minus NAV_TO_SHELL_EXTERNAL minus (passenger network minus origin)
FRESH_PASSENGER_APP_DEFINITION=passenger client process after passengers response (origin excluded)
SAME_COHORT=FRESH_PREVALIDATION
SAME_SAMPLE_PARENT_CHILD=YES
ALL_COMPONENTS_NONNEGATIVE=YES
CHILD_GT_PARENT_COUNT=0
UNATTRIBUTED=0
TOTAL_RECONCILED=YES
FRESH_P95=5072
FRESH_APP_P95=913
FRESH_EXTERNAL_P95=4339
FRESH_PASSENGER_APP_P95=153
FRESH_PASSENGER_EXTERNAL_P95=1392
FRESH_PASSENGER_ORIGIN_P95=3776
FRESH_PASSENGER_UNATTRIBUTED_P95=0
```

`PASS_WITH_DIRECTLY_MEASURED_EXTERNAL_FLOOR` (app P95 ≤ 2000).

## Safety

```
REAL_BOOKING_CREATED=0
REAL_PNR_CREATED=0
REAL_TICKET_CREATED=0
REAL_PAYMENT_CREATED=0
REAL_CANCEL_CREATED=0
REAL_REFUND_CREATED=0
GROUP_REAL_HOLD_CREATED=0
GROUP_REAL_BOOKING_CREATED=0
GROUP_REAL_PAYMENT_CREATED=0
SUPPLIER_MUTATION_CALLS=0
AI_SUPPLIER_MUTATION_CALLS=0
```

No passwords, cookies, session IDs, or CSRF tokens recorded.

## True stop gate

```
BACK_BFCACHE_MATRIX != PASS
PERFORMANCE_DECOMPOSITION = PASS (local recompute)
TEST_FAILURES = auth-email focused 0 (full affected suite not completed this addendum)
COMPANY_PROFILE = PASS (text fields; logo upload N/A this pass)
AUTHENTICATED_ADMIN_UAT_OWNER_HOLD_COUNT = 0
OPEN_COUNT > 0
```

`MASTER_FINAL_STATUS` is **not** PASS.
