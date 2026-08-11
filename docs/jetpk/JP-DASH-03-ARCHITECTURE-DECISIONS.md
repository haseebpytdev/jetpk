# JP-DASH-03 — Architecture Decisions (V3)

Concise decision log for Next back-office migration. Laravel remains authoritative for domain logic.

| ID | Decision | Reason | Alternatives | Risk | Owner | Result |
|----|----------|--------|--------------|------|-------|--------|
| AD-001 | Next is presentation + operator workspace only | Avoid duplicate business engines; mature Laravel services already exist | Rewrite booking lifecycle in TS | High drift / money bugs | Full-stack | **Adopted** |
| AD-002 | Session navigation groups sourced from `BackOfficeCapabilitiesPresenter` | Single RBAC-aware IA owner on server; Staff/Admin filtering stays in Laravel | Hard-code nav in Next `nav-config.ts` | RBAC leaks, stale links | Laravel + Next shell | **Adopted** |
| AD-003 | Preview `nav-config.ts` mirrors presenter groups | Deterministic local preview without Laravel session | Remove preview nav | Preview drift | Next | **Adopted** |
| AD-004 | Booking drawer retained as quick-view; canonical management at `/bookings/[id]` | Complex workflows need full page; drawer OK for list context | Drawer-only management | Buried ops actions | Next bookings | **Adopted** |
| AD-005 | Operational mutations via existing Laravel intake JSON routes | Policies, validation, audit already implemented | New parallel API layer | Security regression | Laravel intake | **Adopted** |
| AD-006 | Payment/refund forms require operator-entered amount + booking currency | Removes hardcoded QA amounts; aligns with money integrity gate | Fixed demo amounts | Financial mis-posting | Next + Laravel | **Adopted** |
| AD-007 | Temporary OTP-off via `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` | Authorized QA window only; must restore before final PASS | Hidden auth route | Security exposure if not restored | Auth config | **Closed for QA window** — OTP required restored `true`; authorized `OTP_DEMO_*` retained from pre-cleanup backup |
| AD-008 | QA identities via Artisan commands, credentials in local vault | Preserves hashing, RBAC sync, no secrets in git | Raw DB inserts | Credential leak | Ops tooling | **Adopted** |
| AD-009 | Commercial supplier/ticket/refund actions proven via backend tests only | Production QA must not mutate commercial state | Click-through prod tests | Real PNR/ticket risk | QA policy | **Adopted** |
| AD-010 | Legacy Admin/Staff Blade shells redirect to Next after parity | Retire presentation only; keep controllers/services; **no active Blade operator shell** | Delete Laravel admin routes | Broken bookmarks | Legacy retirement | **In progress (reopened)** — exhaustive matrix rebuild + redirects required |
| AD-011 | Inter as platform typeface via shared theme tokens | Brand consistency across public + portals + dashboard | Space Grotesk primary | Visual inconsistency | Design system | **Adopted** (prod verified previously; re-verify at true closure) |
| AD-012 | DB branding logo via existing public config pipeline | Avoid hard-coded text logos when upload exists | Static SVG only | Wrong brand in prod | Public + dashboard | **Adopted** (prod verified previously; re-verify at true closure) |
| AD-013 | Commercial safety does **not** authorize retaining Laravel Blade as operator presentation | V3 sole Next shell; dangerous actions stay backend-tested / click-prohibited, but safe operational Next UX is still required | Keep Blade handoffs as "temporary" forever | Invalid ENGINEERING_ACCEPTANCE | Architecture | **Adopted (reopen)** |
| AD-014 | JP-CMS-02 broad Page Builder remains deferred | Out of JP-DASH-03 scope | Expand CMS builder now | Scope explosion | Product | **Adopted** — only operational CMS capability required by JP-DASH-03 |

## Open items (blocking ENGINEERING_ACCEPTANCE)

- Exhaustive legacy retirement matrix with no PENDING/UNKNOWN; prove sole Next Admin/Staff UI gates.
- Convert remaining PARTIAL parity rows that require Next presentation (not Laravel handoff).
- Remove all active Next nav / LiveRedirect paths to Blade back-office presentation.
- Final SEC cleanup + QA identity deactivate only after engineering + unattended browser QA complete.

## Explicit non-exception

Do **not** broaden the payment-drawer empty-ledger evidence exception to unrelated incomplete UI capabilities.
