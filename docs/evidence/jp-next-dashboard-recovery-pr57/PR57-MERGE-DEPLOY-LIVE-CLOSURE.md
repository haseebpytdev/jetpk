# PR57 Merge → Deploy → Live QA Closure

```text
REPOSITORY=haseebpytdev/jetpk
PR=57
PR57_MERGED=YES
CERTIFIED_PR_HEAD=511586cce9dcc71e227f989c166038a06756e8a7
MERGED_MAIN=bc37a9067720daa144509d0f3bc381497120e62c
REMOTE_MAIN_SHA=bc37a9067720daa144509d0f3bc381497120e62c
PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
AUTHORIZED_SHA=bc37a9067720daa144509d0f3bc381497120e62c
DEPLOY_MARKER=pr57-merged-main-bc37a906-20260930T080534Z
PUBLIC_BUILD_ID=UD3rio8m0BUNp2smzW_ia
DASHBOARD_BUILD_ID=0BsJSFA8aLCHBLkg43AM9
CERTIFIED_TREE_EQUIVALENT=YES
CERTIFIED_HEAD_PRESENT_IN_MERGED_MAIN=YES
```

## Pre-deploy / rollback

```text
PREDEPLOY_RUNTIME_SHA=3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492
PREDEPLOY_PUBLIC_BUILD_ID=QfGNA8lxtL9hm6ceW3Rvi
PREDEPLOY_DASHBOARD_BUILD_ID=gwLT6IakIh_aK1-szGgux
BACKUP_TS=20260930T075557Z
ROLLBACK_POINT=3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260930T080534Z
PRE_PROXY_GATE=PASS
```

Note: `.jetpk-rollback-sha` on server retained an older historical value (`cbd7686f…`); operational rollback for this release is the pre-deploy runtime SHA / backup above.

## Deploy notes

- Deployed **MERGED_MAIN** only (not certified branch head alone).
- Scoped stage then full `dashboard/` tree sync from MERGED_MAIN (required for complete Next Dashboard recovery sources).
- Next builds as `pkjetp`; public then dashboard; dashboard restart only after successful build.
- Temporary QA-window controls (restored after live QA):
  - login `throttle:6,1` → `60,1` → restored `6,1`
  - `OTP_DEMO_ALLOW_PRODUCTION` allowlisted to `jp-dash-03-qa-*` only → restored from `.env` backup

## Live public Golden

```text
LIVE_PUBLIC_GOLDEN=PASS
LIVE_PUBLIC_500=0
LIVE_PUBLIC_FATAL_JS=0
FINAL_PUBLIC_GOLDEN=PASS
PUBLIC_GOLDEN_REGRESSIONS=0
```

Verified: `/`, login, register, forgot-password, booking lookup, groups, Search Flights UI, Ask JetPakistan entry, homepage Golden sections (trending/destinations/deals).

## Live role QA

```text
QA_ADMIN_LIVE=PASS
QA_STAFF_LIVE=PASS
QA_AGENT_LIVE=PASS
QA_CUSTOMER_LIVE=PASS
LIVE_RBAC_SANITY=PASS
```

Supporting local probe artifacts (not required on `main` for closure authority):

- sanitized role/RBAC probe JSON retained under agent recovery workspace

Admin supplemental surfaces confirmed 200: api-connections, group-ticketing, settings/integrations, plus overview/bookings/CMS/SEO/settings/security/notifications/customer-queries/staff/markups/go-live/profile.

Wrong-path probes (not product regressions): `/admin/dashboard/ai`, `/admin/dashboard/groups` (canonical group path `/admin/dashboard/group-ticketing`).

RBAC semantics: non-admin on `/admin/*` receives Preview shell without admin authority; customer→`/agent/dashboard` redirects to `/customer/bookings`; unauthenticated admin shell is Preview-gated.

```text
LIVE_UNEXPECTED_500=0
LIVE_HYDRATION_ERRORS=0
LIVE_CHUNK_FAILURES=0
LIVE_FATAL_CONSOLE_ERRORS=0
LIVE_FAILED_FETCHES=0
LIVE_REDIRECT_LOOPS=0
STAFF_PRODUCTION_PRIVILEGE_ESCALATION=0
QA_AGENT_WALLET_MUTATION=0
CUSTOMER_CROSS_ACCOUNT_ACCESS=0
```

## Commercial safety

```text
REAL_TICKETS_ISSUED=0
REAL_PAYMENTS_TRIGGERED=0
REAL_SUPPLIER_BOOKINGS_CREATED=0
REAL_PNRS_MUTATED=0
REAL_CANCELLATIONS=0
REAL_REFUNDS=0
PRODUCTION_BALANCE_MUTATIONS=0
```

## QA cleanup

```text
QA_ACTIVE_SESSIONS_AFTER_CLEANUP=0
QA_IDENTITIES_ACTIVE_AFTER_CLEANUP=0
```

All four `jp-dash-03-qa-*` identities suspended; sessions + remember tokens invalidated; temporary OTP demo override restored; login throttle restored to `6,1`; local process password env cleared.

## Supplemental verification

Grok verifier returned PASS after durable RBAC/cleanup evidence was persisted (agent transcript reference available in the recovery session). Repository/runtime markers above remain authoritative.

## Final status

```text
JETPAKISTAN NEXT DASHBOARD FULL STATE RECOVERY
FINAL_STATUS=VERIFIED_PASS
FINAL_EVIDENCE_PUBLISHED=PENDING_PROTECTED_MAIN_MERGE
```
