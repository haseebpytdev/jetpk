# OWNER UAT WAVE 1 — Final Report

## Result

`OWNER_UAT_WAVE_1=PASS_READY_FOR_FINAL_OWNER_ACCEPTANCE`

Owner accepted Agent/Customer portal structure. Final polish loop completed:

- signed-out header **Login** only
- authenticated header without Book Now / Login
- Agent + Customer compact welcome panels

See `OWNER-UAT-W1-OWNER-POLISH-EVIDENCE.md`, `OWNER-UAT-W1-AUTH-BROWSER-EVIDENCE.md`, `OWNER-UAT-W1-PORTAL-LAYOUT-EVIDENCE.md`.

## Branch / baseline

| Item | Value |
|---|---|
| Wave-1 branch | `phase/jetpk-owner-uat-wave-1-portals-public-shell` |
| Wave-1 HEAD | branch tip on `jetpk` (includes auth + portal width + owner polish) |
| Frozen JP-UAT-01 remote | `phase/jetpk-uat-01-autonomous-business-uat` @ `d1085ed6e9ae0764033ea35928b8d6804f2d0f0b` (untouched) |
| Remote | `jetpk` (not `origin`) |

## Auth carried forward

- QA Staff / Agent / Customer: **active**
- `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` (temporary Owner UAT)
- `OTP_DEMO_*` preserved
- Real-browser login regression required for Agent / Customer + Staff quick check

## Structural owner acceptance (prior)

| Item | Status |
|---|---|
| Agent portal width/layout | ACCEPTED |
| Customer portal width/layout | ACCEPTED |
| Customer Overview landing | ACCEPTED |
| Compact authenticated footer | ACCEPTED |
| Grouped/collapsible navigation | ACCEPTED |
| Signed-in shell structure | ACCEPTED (with polish below) |

## Final owner-polish gates

| Gate | Status |
|---|---|
| OWNER_W1_SIGNED_OUT_LOGIN_CTA | PASS |
| OWNER_W1_GLOBAL_SIGNUP_REMOVED | PASS |
| OWNER_W1_AUTHENTICATED_BOOK_NOW_REMOVED | PASS |
| OWNER_W1_PROFILE_DROPDOWN | PASS |
| OWNER_W1_HEADER_RESPONSIVE | PASS |
| OWNER_W1_AGENT_WELCOME_PANEL | PASS |
| OWNER_W1_CUSTOMER_WELCOME_PANEL | PASS |
| OWNER_W1_HERO_MEDIA_NONBLOCKING | PASS (CSS/SVG slot; no blocking image) |
| OWNER_W1_PORTAL_VISUAL_HIERARCHY | PASS |
| OWNER_W1_PORTAL_RESPONSIVE | PASS |
| OWNER_W1_AUTH_REGRESSION | PASS |
| OWNER_W1_SOURCE_PARITY | PASS (intended Wave-1 frontend files deployed + rebuilt) |
| OWNER_W1_OLS_INTEGRITY | PASS |
| OWNER_W1_NO_COMMERCIAL_QA_SIDE_EFFECTS | PASS |

## Prior Wave-1 gates (preserved)

| Gate | Status |
|---|---|
| OWNER_W1_AGENT_PORTAL | PASS |
| OWNER_W1_AGENT_STAFF_SHARED_SHELL | PASS |
| OWNER_W1_AGENT_RBAC_NAVIGATION | PASS |
| OWNER_W1_CUSTOMER_PORTAL | PASS |
| OWNER_W1_CUSTOMER_RBAC_NAVIGATION | PASS |
| OWNER_W1_OTA_REFERENCE_PARITY | PASS |
| OWNER_W1_SIGNED_IN_HEADER | PASS |
| OWNER_W1_LEGACY_PUBLIC_RENDERINGS | 0 |
| OWNER_W1_PUBLIC_UNKNOWN_ROUTES | 0 |
| OWNER_W1_ERROR_PAGE_MATRIX | PASS |

## OLS

`OWNER_UAT_AUTH_OLS_INTEGRITY=PASS`  
`sha256sum /usr/local/lsws/conf/httpd_config.conf` =  
`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Docs

- `docs/jetpk/OWNER-UAT-W1-OTA-PORTAL-PARITY.md`
- `docs/jetpk/OWNER-UAT-W1-PUBLIC-LEGACY-MATRIX.md`
- `docs/jetpk/OWNER-UAT-W1-PUBLIC-LEGACY-MATRIX.json`
- `docs/jetpk/OWNER-UAT-W1-OWNER-POLISH-EVIDENCE.md`

## Owner final visual inspect

1. Public header signed-out → Login only
2. Public header signed-in → profile dropdown; no Book Now
3. Agent overview welcome panel + KPIs below
4. Customer overview welcome panel + KPIs below

Do **not** start Wave 2 automatically.

Not launch / not OWNER UAT COMPLETE.
