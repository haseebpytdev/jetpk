# JP-DASH-03 — Architecture Decisions (V3)

Concise decision log for Next back-office migration. Laravel remains authoritative for domain logic.

| ID | Decision | Reason | Alternatives | Risk | Owner | Result |
|----|----------|--------|--------------|------|-------|--------|
| AD-001 | Next is presentation + operator workspace only | Avoid duplicate business engines; mature Laravel services already exist | Rewrite booking lifecycle in TS | High drift / money bugs | Full-stack | **Adopted** |
| AD-002 | Session navigation groups sourced from `BackOfficeCapabilitiesPresenter` | Single RBAC-aware IA owner on server; Staff/Admin filtering stays in Laravel | Hard-code nav in Next `nav-config.ts` | RBAC leaks, stale links | Laravel + Next shell | **Adopted** (Wave 1) |
| AD-003 | Preview `nav-config.ts` mirrors presenter groups | Deterministic local preview without Laravel session | Remove preview nav | Preview drift | Next | **Adopted** |
| AD-004 | Booking drawer retained as quick-view; canonical management at `/bookings/[id]` | Complex workflows need full page; drawer OK for list context | Drawer-only management | Buried ops actions | Next bookings | **Adopted** (Wave 3 start) |
| AD-005 | Operational mutations via existing Laravel intake JSON routes | Policies, validation, audit already implemented | New parallel API layer | Security regression | Laravel intake | **Adopted** |
| AD-006 | Payment/refund forms require operator-entered amount + booking currency | Removes hardcoded QA amounts; aligns with money integrity gate | Fixed demo amounts | Financial mis-posting | Next + Laravel | **Adopted** (Wave 1) |
| AD-007 | Temporary global OTP-off via `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` | Authorized QA window; no bypass endpoints | Hidden auth route | Security exposure if not restored | Auth config | **Active QA only** |
| AD-008 | QA identities via Artisan commands, credentials in local vault | Preserves hashing, RBAC sync, no secrets in git | Raw DB inserts | Credential leak | Ops tooling | **Adopted** |
| AD-009 | Commercial supplier/ticket/refund actions proven via backend tests only | Production QA must not mutate commercial state | Click-through prod tests | Real PNR/ticket risk | QA policy | **Adopted** |
| AD-010 | Legacy Admin/Staff Blade shells redirect to Next after parity | Retire presentation only; keep controllers/services | Delete Laravel admin routes | Broken bookmarks | Legacy retirement | **Pending** (Wave 6) |
| AD-011 | Inter as platform typeface via shared theme tokens | Brand consistency across public + portals + dashboard | Space Grotesk primary | Visual inconsistency | Design system | **In progress** |
| AD-012 | DB branding logo via existing public config pipeline | Avoid hard-coded text logos when upload exists | Static SVG only | Wrong brand in prod | Public + dashboard | **Pending verify** |

## Open items

- **Booking management depth**: full lifecycle timeline, documents, communications, and audit panels need Laravel read APIs or enriched booking detail payload.
- **Markups / commissions / go-live**: may remain Laravel-handoff routes until dedicated Next modules ship.
- **OTP restoration**: mandatory before `ENGINEERING_ACCEPTANCE=PASS`.
