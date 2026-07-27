# Dashboard Laravel Auth Integration Roadmap — JETPK-DASH-10 Prompt 02

Phase: **JETPK-DASH-10 Prompt 02** (RBAC + settings UI foundation documented)

This document describes how the JetPakistan Next dashboard preview will connect to **Laravel as the authoritative authentication and RBAC layer**. Prompt 02 delivers full fixture-backed RBAC and settings UI with local-preview editing; Laravel connectivity remains future work.

Existing related doc: [`auth-rbac-integration-plan.md`](./auth-rbac-integration-plan.md) (DASH-01 overview).

---

## Current state (Prompt 02)

| Area | Preview (implemented) | Laravel (future) |
|------|----------------------|------------------|
| Authentication | None (open `/testdash`) | Session on `/admin`, `/staff` |
| User directory | 40 fixture users, filters, drawer | Platform admin + staff accounts |
| Roles page | 14 fixture roles, matrix, comparison, permission preview | Live role definitions |
| Permissions page | 46-permission catalog, filters, drawer | Aligned with `StaffPermission` / policies |
| RBAC catalog | 46 permissions, 14 roles, `mockRolePermissions` | `StaffPermission`, policies, platform admin gate |
| Access decisions | In-memory `evaluateAccessDecision()` + explainer UI | Server policies/Gates |
| Settings overview | 4 sections, validation readiness metrics | Live config (not exposed) |
| Settings sections | General, security, notifications, integrations with local preview | Laravel admin settings |
| Settings persistence | None (client preview only) | Database / config store |
| Audit | Fixture timeline | Production audit log (future read API) |

### Prompt 02 UI foundation (fixture-backed)

**RBAC surfaces now live:**

- `/testdash/users` — directory with role assignment preview
- `/testdash/users/roles` — role table, permission matrix, role comparison, permission assignment preview, access decision explainer
- `/testdash/users/permissions` — permission catalog table and detail drawer

**Settings surfaces now live:**

- `/testdash/settings` — overview with per-section readiness
- `/testdash/settings/general` — organization metadata + local preview
- `/testdash/settings/security` — MFA/password/session policy metadata + local preview
- `/testdash/settings/notifications` — 9 notification categories + local preview
- `/testdash/settings/integrations` — 8 integration records (status only, no credentials) + local preview

**Services with mock guard:**

| Service | File | Live error code |
|---------|------|-----------------|
| Users | `services/user-service.ts` | `USR-PREVIEW-NO-LIVE` |
| Roles | `services/role-service.ts` | `ROL-PREVIEW-NO-LIVE` |
| Permissions | `services/permission-service.ts` | `PRM-PREVIEW-NO-LIVE` |
| Settings | `services/settings-service.ts` | `SET-PREVIEW-NO-LIVE` |
| Audit | `services/audit-service.ts` | `AUD-PREVIEW-NO-LIVE` |

**Contracts ready for Laravel alignment:**

- `types/access-control.ts` — User, Role, Permission, validation issues
- `types/settings-module.ts` — General, security, notification, integration DTOs
- `lib/access-control/permission-catalog.ts` — 46 permission keys with `laravelPolicyHint`
- `lib/access-control/settings-validation.ts` — validation rules to mirror in Laravel FormRequests
- `mocks/rbac-fixtures.ts` — 14 roles with `ROLE_PERMISSION_MAP`
- `mocks/settings-fixtures.ts` — deterministic settings payloads
- `mocks/audit-fixtures.ts` — **60** synthetic audit events (TEST-NET IPs, `previewOnly: true`)
- `types/audit.ts` — query, summary, export manifest DTOs
- `lib/audit/audit-validation.ts` — event validation rules to mirror in Laravel serializers

See [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md), [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md), and [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md) for full component maps.

---

## Future auth source

**Laravel web session** remains the primary auth mechanism when the dashboard is same-origin mounted (e.g. static export under `public/testdash/`).

| Portal | Laravel entry | Account type | Notes |
|--------|---------------|--------------|-------|
| Platform admin | `/admin` | `platform_admin` | Users, suppliers, settings, full RBAC |
| Staff operations | `/staff` | `staff` | Scoped by `StaffPermission`; never `/admin` URLs |

The dashboard must not implement a parallel login or JWT store. Session cookies issued by Laravel are the credential.

Staff and admin **portal separation** must be preserved in production — a single preview shell is convenience only.

---

## Future read APIs

Proposed JSON endpoints (names illustrative; implement under authenticated web or `/admin/api` group):

| Endpoint | Permission gate | Response |
|----------|-----------------|----------|
| `GET /api/dashboard/session` | authenticated | Current user profile, roles, effective permission keys |
| `GET /api/dashboard/users` | `users.view` | Paginated user directory DTO |
| `GET /api/dashboard/users/{id}` | `users.view` | User detail (no secrets) |
| `GET /api/dashboard/roles` | `roles.view` | Role list with permission counts |
| `GET /api/dashboard/roles/{id}` | `roles.view` | Role detail + permission keys + scopes |
| `GET /api/dashboard/permissions` | `roles.view` or `users.view` | Permission catalog (aligned with `PERMISSION_CATALOG`) |
| `GET /api/dashboard/settings/{section}` | `settings.view` | Settings category DTO |
| `GET /api/dashboard/audit` | `audit.view` | Paginated audit events (masked IP) |

All responses must omit protected fields defined in [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md).

Service swap point: `dashboard/services/user-service.ts`, `role-service.ts`, `permission-service.ts`, `settings-service.ts` replace fixture reads when `useMockData()` is false.

---

## Policies and gates

Map each catalog `laravelPolicyHint` to Laravel implementation:

```text
Permission key ──► Policy method or Gate ──► allow/deny + optional scope
```

Examples from catalog:

- `BookingPolicy::view`, `::cancelApprove`
- `UserPolicy::assignRoles`, `::suspend`
- `SettingPolicy::update`
- `Gate::define('dashboard.view')`

**Not Spatie** — align with existing JetPakistan patterns documented in `docs/audit/admin-rbac-matrix.md` and `StaffPermission` middleware.

Approval permissions (`*.approve`) should return a structured `requires_approval` or workflow state when the user holds request but not approve capability.

---

## Middleware

Server-side enforcement stack (Laravel):

1. `auth` — valid session
2. Portal guard — admin vs staff route group
3. `StaffPermission` or `PlatformModuleGate` — module visibility
4. Policy/`authorize()` on controller actions
5. Optional channel scope middleware for PNR/ticket routes

Next dashboard **must not** rely on client-side checks alone. UI hides controls based on session permission keys; Laravel rejects unauthorized API calls.

---

## Server enforcement vs client presentation

| Concern | Server (Laravel) | Client (Next) |
|---------|------------------|---------------|
| Authentication | Session cookie | Redirect to Laravel login if 401 |
| Authorization | Policy on every API call | Hide nav items, disable buttons |
| Role assignment | `UserPolicy::assignRoles` | Preview diff UI only until Phase C |
| High-risk actions | Explicit authorize + audit | Warning banners, confirmation dialogs |
| Settings update | `SettingPolicy::update` | Local preview form (non-persistent) until Phase C |
| Export | `audit.export`, `reports.export` | Download via Laravel-generated file |

Client `evaluateAccessDecision()` becomes a **presentation helper** fed by server-computed permission keys — not the enforcement source.

---

## Session handling

1. Same-origin deploy so Laravel session cookie reaches dashboard fetches
2. Next server components fetch with forwarded cookies (or BFF route handlers)
3. Session DTO includes: user id, display name, role keys, permission keys, portal type
4. Session refresh/timeout follows Laravel config (`sessionDurationHours` metadata mirrors policy)
5. Suspension and lockout enforced server-side; dashboard receives updated status on next fetch

No storage of session tokens in `localStorage` or `NEXT_PUBLIC_*` variables.

---

## CSRF

Mutating requests (future phases) require:

1. Laravel issues CSRF token (meta tag or `/api/dashboard/csrf`)
2. Next client includes `X-XSRF-TOKEN` or `_token` on POST/PATCH/DELETE
3. Double-submit cookie pattern for same-origin fetches

Read-only GET endpoints in Phase A may use session auth without CSRF; all mutations require CSRF from Phase C onward.

---

## Audit generation

When live integration is enabled, align emitted events with the **40-type catalog** in [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md):

| Event | Trigger | Notes |
|-------|---------|-------|
| `user.recordViewed` | User detail API | Actor + target user id |
| `user.rolePreviewApplied` | Role assignment preview submit (Phase C) | Before/after role ids — real persist only after policy check |
| `permission.matrixViewed` | Permissions catalog API | Compliance access |
| `settings.viewed` / `settings.*PreviewChanged` | Settings section load / PATCH | Section key only; no secrets in payload |
| `security.warningDetected` | Failed policy check | No secret in payload |
| `permission.authorizationPreviewEvaluated` | Access decision API | Authorization outcome + permission key |

**Audit integration notes:**

- Dashboard `audit-service.ts` swaps to `GET /api/dashboard/audit` in Phase D; until then fixtures only
- Server writes audit records; `metadata.previewOnly` absent on real events; `syntheticSource` not serialized
- Apply TEST-NET-compatible IP masking **before** persistence and JSON export
- `audit.export` generates server-side CSV using manifest columns from `AUDIT_EXPORT_COLUMNS` — not client-side fixture export
- Retention categories (`standard`, `security`, `compliance`, `operational`) drive Laravel retention jobs; `auditRetentionDays` in settings is authoritative in production
- Validation rules in `lib/audit/audit-validation.ts` should be mirrored in Laravel audit serializers (no unmasked IP, no secret metadata keys)

---

## Migration sequence

### Phase A — Read-only session API

1. Add session + permission DTO endpoint
2. Mount dashboard same-origin; forward cookies
3. Swap `getUsersModule()` to Laravel GET with mock fallback flag
4. Gate each endpoint with existing policies
5. Filter nav via server-provided allowed modules

**Exit criteria:** Authenticated admin can browse `/testdash/users` with live directory; unauthenticated users redirected to Laravel login.

### Phase B — RBAC presentation parity

1. Live roles and permissions endpoints aligned with `PERMISSION_CATALOG` keys
2. Effective access computed server-side (or verified against server)
3. Roles and permissions pages (`/testdash/users/roles`, `/testdash/users/permissions`) — **UI complete in Prompt 02**; swap fixture services to Laravel GET
4. Settings GET endpoints aligned with `settings-module.ts` DTOs — **UI complete in Prompt 02**
5. Staff portal variant with reduced nav tree

**Exit criteria:** Permission keys in API match catalog (46); role count matches production model; staff cannot see admin-only modules; settings JSON omits credentials.

### Phase C — Controlled mutations

1. User invite/update, role assignment, role permission assignment, settings update via Laravel POST/PATCH
2. CSRF on all mutations
3. High-risk confirmations + audit entries
4. Session revocation on suspend
5. Settings PATCH per section; credential rotation remains in Laravel admin only

**Exit criteria:** Role assignment persists; settings preview replaced by server-validated save; audit log records change; policies enforce separation of duties.

### Phase D — Audit and export

1. Live audit timeline API with pagination
2. Server-side export for audit and reports
3. Compliance review of masked IP and retention policy

**Exit criteria:** `audit.export` and report exports audited and policy-gated.

---

## Non-goals

The following are **explicitly out of scope** for dashboard–Laravel auth integration:

- Replacing Laravel login with NextAuth or external IdP (unless separate program)
- Spatie Permission package adoption (existing RBAC model retained)
- Exposing supplier credentials or payment capture in dashboard JSON
- Merging admin and staff portals into one production URL space
- Client-only RBAC enforcement without policy backing
- Persisting preview role-assignment or permission-assignment experiments
- Persisting settings local-preview edits
- Exposing integration credentials in dashboard settings JSON
- Real-time websocket session sync in initial phases
- Production deploy or SFTP upload from this documentation phase

---

## Alignment checklist

Before each integration phase:

- [ ] Permission keys match [`RBAC-PERMISSION-CATALOG.md`](./RBAC-PERMISSION-CATALOG.md)
- [ ] Protected fields absent per [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md)
- [ ] Policies registered for new endpoints
- [ ] CSRF on mutations
- [ ] Audit events for sensitive reads/writes
- [ ] Staff cannot access admin-only routes
- [ ] Playwright smoke tests updated for live/mock toggle

---

## Related documentation

- [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md) — audit event catalog and live integration
- [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md)
- [`RBAC-PERMISSION-CATALOG.md`](./RBAC-PERMISSION-CATALOG.md)
- [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md)
- [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md)
- [`architecture.md`](./architecture.md)
- [`preview-routing.md`](./preview-routing.md)
- [`mock-data-policy.md`](./mock-data-policy.md)
