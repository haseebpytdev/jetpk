# OWNER UAT WAVE 1 — Final Report

## Result

`OWNER_UAT_WAVE_1=PASS_READY_FOR_OWNER_RETEST`

Auth browser gate reopened and re-proven after owner Network-error report. See `OWNER-UAT-W1-AUTH-BROWSER-EVIDENCE.md`.

## Branch / baseline

| Item | Value |
|---|---|
| Wave-1 branch | `phase/jetpk-owner-uat-wave-1-portals-public-shell` |
| Wave-1 HEAD | branch tip on `jetpk` (includes auth `f874b5d` + feature `72cd954`) |
| Frozen JP-UAT-01 remote | `phase/jetpk-uat-01-autonomous-business-uat` @ `d1085ed6e9ae0764033ea35928b8d6804f2d0f0b` (untouched) |
| Remote | `jetpk` (not `origin`) |

## Auth carried forward

- QA Staff / Agent / Customer: **active**
- `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` (temporary Owner UAT)
- `OTP_DEMO_*` preserved
- Cold JSON login re-proven:
  - Staff → `/staff/dashboard`
  - Agent → `/agent`
  - Customer → `/customer/bookings`
- Agent JSON `/laravel/agent?format=json` → 200 with grouped `navigation`
- Customer JSON `/laravel/customer/bookings?format=json` → 200

## Gates

| Gate | Status |
|---|---|
| OWNER_W1_AGENT_PORTAL | PASS |
| OWNER_W1_AGENT_STAFF_SHARED_SHELL | PASS |
| OWNER_W1_AGENT_RBAC_NAVIGATION | PASS |
| OWNER_W1_CUSTOMER_PORTAL | PASS |
| OWNER_W1_CUSTOMER_RBAC_NAVIGATION | PASS |
| OWNER_W1_OTA_REFERENCE_PARITY | PASS |
| OWNER_W1_SIGNED_IN_HEADER | PASS |
| OWNER_W1_PUBLIC_HEADER_RESPONSIVE | PASS (compact controls + nowrap Book Now; owner retest widths) |
| OWNER_W1_LEGACY_PUBLIC_RENDERINGS | 0 |
| OWNER_W1_PUBLIC_UNKNOWN_ROUTES | 0 |
| OWNER_W1_ERROR_PAGE_MATRIX | PASS |
| OWNER_W1_AUTH_REGRESSION | PASS |
| OWNER_W1_NO_COMMERCIAL_QA_SIDE_EFFECTS | PASS |
| OWNER_W1_SOURCE_PARITY | PASS (intended Wave-1 files deployed + rebuilt) |
| OWNER_W1_OLS_INTEGRITY | PASS |

## OLS

`OWNER_UAT_AUTH_OLS_INTEGRITY=PASS`  
`sha256sum /usr/local/lsws/conf/httpd_config.conf` =  
`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Docs

- `docs/jetpk/OWNER-UAT-W1-OTA-PORTAL-PARITY.md`
- `docs/jetpk/OWNER-UAT-W1-PUBLIC-LEGACY-MATRIX.md`
- `docs/jetpk/OWNER-UAT-W1-PUBLIC-LEGACY-MATRIX.json`

## Owner retest focus

1. Agent dashboard — compact KPIs, grouped RBAC nav, wallet/attention panels
2. Customer dashboard — compact overview + grouped nav
3. Public signed-in header — no standalone Dashboard link; profile Overview; compact theme/PKR; Book Now
4. Unknown URL → Next branded not-found via `/access-denied?reason=not-found`

Not launch / not OWNER UAT COMPLETE.
