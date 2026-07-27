# Users Security and Privacy Rules — JETPK-DASH-10 Prompt 02

Phase: **JETPK-DASH-10 Prompt 02**

These rules govern what the JetPakistan dashboard preview **may display**, **must never expose**, and **must not persist**. They apply to `/testdash/users`, `/testdash/users/roles`, `/testdash/users/permissions`, `/testdash/settings/*`, and `/testdash/audit`. Laravel remains the future authoritative layer for enforcement.

---

## Protected fields

The following must **never** appear in fixtures, API responses, UI, exports, or audit metadata in the preview:

| Category | Prohibited data |
|----------|-----------------|
| Authentication secrets | Passwords, password hashes, recovery tokens, MFA TOTP seeds, backup codes, API keys, bearer tokens |
| Session internals | Session IDs, cookie values, CSRF tokens, refresh tokens, device fingerprints |
| Supplier credentials | Sabre/NDC credentials, LNIATA, PCC, webhook signing secrets |
| Payment capture | Card numbers, CVV, bank account numbers, gateway secrets |
| Raw network identity | Full client IP addresses in user-facing UI (use masked form only) |

### Allowed security metadata (read-only)

These fields are **safe to show** as operational metadata:

- `mfaState`, `mfaRequired` (not the secret itself)
- `failedSignInCount`, `activeSessionCount` (counts only)
- `lastSignInAt` (timestamp)
- Password **policy** settings (`passwordMinLength`, lockout thresholds) — not user passwords
- Integration **status** labels (`configured_preview`, `enabled`) — not credentials

Settings contracts in `lib/access-control/settings-contracts.ts` mark all fields `readOnly: true`; none are `sensitive: true` in fixtures because secrets are excluded entirely.

---

## Settings security boundaries

Settings pages (`/testdash/settings/*`) display **policy and readiness metadata only**. They must never become a credential management surface in the preview.

### What settings may show

| Section | Allowed metadata | Source |
|---------|------------------|--------|
| General | Organization name, support email/phone, timezone, currency, locale, date format | `FIXTURE_GENERAL_SETTINGS` |
| Security | MFA policy labels, password length/complexity rules, session/lockout thresholds, audit retention | `FIXTURE_SECURITY_SETTINGS` |
| Notifications | Category enablement, channel toggles (email/dashboard), severity, recipient role keys, delivery mode | `FIXTURE_NOTIFICATION_SETTINGS` |
| Integrations | Supplier name, readiness status, environment label, completeness %, capability summary (no secrets) | `FIXTURE_INTEGRATION_SETTINGS` |

### What settings must never show or accept

| Category | Prohibited in settings UI and fixtures |
|----------|----------------------------------------|
| Supplier credentials | Sabre PCC, LNIATA, NDC API keys, One API tokens |
| Email transport | SMTP host/password, Mailgun/SendGrid API keys |
| Payment gateway | Merchant IDs, capture secrets, webhook signing keys |
| Webhooks | Endpoint URLs with auth tokens, HMAC secrets |
| Auth secrets | Passwords, MFA seeds, recovery codes, session tokens |

### Enforcement mechanisms (Prompt 02)

1. **Fixture design** — `settings-fixtures.ts` contains only status labels and policy metadata; integration `capabilitySummary` explicitly notes absence of credentials (e.g. email delivery: *"no SMTP credentials in preview"*)
2. **Contract flags** — `settings-contracts.ts` fields are `readOnly: true`, `sensitive: false`
3. **Validation guard** — `validateIntegrationSettings()` in `settings-validation.ts` rejects `SECRET_PATTERN` matches (`password`, `secret`, `token`, `apikey`, `pcc`, `lniata`, `smtp`, `webhook`) in integration keys and capability summaries
4. **Non-persistence** — `SettingsLocalPreviewForm` edits are client-side only; refresh restores fixtures; no API writes
5. **Permission gate (future)** — `settings.update` is `isHighRisk: true` in the catalog; preview ignores it because no save path exists
6. **Smoke tests** — DOM must not contain password/secret/Bearer/sessionId strings

### Local-preview editing boundaries

`SettingsLocalPreviewForm` allows editing policy metadata (org name, MFA policy label, notification channels, integration readiness) for **validation QA only**:

- Changes are held in React state (`previewValues`)
- "Apply preview" runs section validators against draft values
- "Reset" or page refresh restores `settings-fixtures.ts` baseline
- No `localStorage`, no fixture file writes, no Laravel PATCH

Even if a user previews `security.passwordMinLength = 6` or disables MFA policy in the form, **no authentication behavior changes** — Laravel remains authoritative.

### Integration metadata rules

The 8 integration records in `FIXTURE_INTEGRATION_SETTINGS` follow these rules:

- `readinessStatus` uses human labels (`Configured (preview)`, `Not configured`, `Preview only`) — not connection strings
- `environmentLabel` is one of `preview`, `staging`, `production`, `not_configured`
- `configurationCompleteness` is a percentage metadata field — not a credential checklist
- `documentationReference` points to internal docs paths — not live admin URLs with tokens
- Disabled integrations (`paymentProvider`, `webhookReadiness`) demonstrate incomplete-state validation without exposing secrets

---

## Prohibited values

### Branding and identity

- No Parwaaz Travels, YoursDomain, YD Travel, haseeb-master, or unrelated client branding in user-facing copy, emails, or fixtures.
- Organization display name must remain **JetPakistan** (`ACCESS_BRAND.label`).
- Fixture emails use `@staff-preview.jetpakistan.example` — never production staff emails.

### Placeholder and demo wording

- No "lorem ipsum", "demo user", "test123", or placeholder passwords in production-facing UI strings.
- Preview disclaimers may state "fixture-backed preview" — that is required transparency, not demo filler.

### Dangerous capability strings

Smoke tests reject DOM content matching: `password`, `passwordHash`, `Bearer`, `sessionId`, `cookie` (case-insensitive). Do not add fixture fields that would fail this gate.

---

## Session privacy

### What the preview shows

| Field | Rule |
|-------|------|
| `session.activeSessionCount` | Integer count only |
| `session.lastSignInAt` | ISO timestamp or null |
| `session.lastSignInMaskedLocation` | Documentation-range IP label, e.g. `192.0.2.10 (documentation range)` — RFC 5737 TEST-NET |

### What the preview hides

- Individual session records, user agents, device names, geo coordinates
- Ability to revoke sessions (future Laravel action; not in Prompt 01)
- Cross-user session correlation

### Suspended account rule

If `security.status === "suspended"` and `activeSessionCount > 0`, validation raises blocking error `USER_SUSPENDED_ACTIVE_SESSION`. In production, Laravel must revoke sessions on suspension; the preview only surfaces the inconsistency for QA.

---

## IP masking

Audit events and user session summaries use **masked or documentation-range IPs** only:

- Audit fixtures: values like `192.0.2.10`, `198.51.100.5`, `203.0.113.15` (TEST-NET / documentation blocks)
- `AuditMetadata.maskedIp` may be `null` when location is unavailable
- Never store or render full IPv4/IPv6 from production logs in fixtures

Future Laravel integration should apply server-side masking before JSON serialization to the dashboard API.

---

## MFA and password protection

### MFA

- Display `mfaState`: `enabled`, `disabled`, `required`, `pendingSetup`
- When `mfaRequired === true` and `mfaState !== "enabled"`, validation blocks with `USER_MFA_REQUIRED_DISABLED`
- Settings show MFA **policy** (`required_for_admin`) — not enrollment URLs or secrets
- No MFA bypass controls in preview UI

### Passwords

- Password fields are **absent** from the user model and all drawers/tables
- Password policy appears only under `/testdash/settings/security` as numeric metadata
- No "reset password" or "set password" actions in Prompt 01

---

## Role-assignment boundaries

### Protected roles

| Role | ID | Rule |
|------|-----|------|
| Super Administrator | `JP-ROL-0001` | `isProtected: true`; assignment to users without existing roles triggers preview warning `PREVIEW_PROTECTED_ROLE` |
| Read-only Auditor | `JP-ROL-0009` | `isProtected: true`; system role, not modifiable in production |

### Assignment preview (non-persistent)

- `RoleAssignmentPreview` is client-side only; refresh restores fixture assignments
- Duplicate role selection is a blocking preview error
- Combining operations roles with audit roles in large multi-role sets triggers `PREVIEW_CONFLICTING_ROLES` warning
- `users.assignRoles` and `roles.assignPermissions` are high-risk — only Super Administrator holds `roles.assignPermissions` in fixtures

### Separation of duties (guidance)

- Request permissions (cancel, refund, issue, publish) should be held by operational roles
- Matching approve permissions should be limited to senior/admin roles
- Read-only Auditor should not receive mutation or assign permissions in production

---

## High-risk permissions

Ten permissions are flagged `isHighRisk`. Additional UI and future server rules apply:

| Permission | Risk | Preview behavior |
|------------|------|------------------|
| `bookings.cancel.approve` | Approval gate | `requiresApproval: true` in access decision |
| `payments.refund.approve` | Financial | Same |
| `pnrs.cancel.approve` | Channel + approval | Same |
| `tickets.issue.approve` | Ticketing | Same |
| `cms.publish.approve` | Public content | Same |
| `users.suspend` | Account lockout | Validation + drawer warning |
| `users.assignRoles` | Privilege escalation | AD role in fixtures; not booking agents |
| `roles.assignPermissions` | Privilege escalation | SA only |
| `settings.update` | Platform config | Read-only settings UI + local preview only; no save |
| `audit.export` | Data egress | SA only; no export button in preview |

Users with ≥6 high-risk effective permissions trigger `USER_EXCESSIVE_HIGH_RISK` validation warning.

---

## Audit privacy

See [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md) for the full audit domain (event model, export manifest, validation, retention).

### Audit event content

Preview audit events (`mocks/audit-fixtures.ts`, **60 fixtures**) include:

- Actor display name and role label — no actor email in table summary (structured `userId` for cross-links only)
- `metadata.previewOnly: true` on all events; `metadata.syntheticSource: "fixture"`
- `metadata.maskedIp` / `metadata.maskedNetworkRange` — TEST-NET only (`192.0.2.x`, `198.51.100.x`, `203.0.113.x`)
- Outcome `preview` for simulated actions; summaries must not claim live mutations

### Restricted audit data

- Do not log password changes, MFA secret enrollment, or raw request payloads
- Do not include supplier credentials, payment instrument data, or session tokens in audit metadata
- Export manifest (`AUDIT_EXPORT_COLUMNS`) excludes email, full IP, and secrets — **15 approved columns only**
- `audit.export` is catalogued but **not executable** in preview (manifest preview only)

### Validation enforcement

`validateAuditEvent()` blocks: unmasked IPs (`AUDIT_UNMASKED_IP`), secret metadata keys (`AUDIT_SECRET_METADATA`), missing preview markers on preview mutation types, and fake mutation wording in summaries. Foundation coverage: `audit-security.foundation.spec.ts`.

### Viewer access

- `audit.view` granted to Super Administrator, Administrator, Read-only Auditor in fixtures
- Audit UI at `/testdash/audit` is read-only timeline; Laravel will enforce `audit.view` on live API

---

## Fixture-only rules

1. **No persistence** — user, role, permission, and settings changes are not saved; URL state, drawer selection, and client preview state only.
2. **Mock guard** — `user-service.ts`, `role-service.ts`, `permission-service.ts`, `settings-service.ts` throw if `useMockData()` is false.
3. **Deterministic edge cases** — intentional invalid users (e.g. `JP-USR-0015`, `JP-USR-0023`, `JP-USR-0037`) exist for validation QA; do not "fix" them without updating tests.
4. **No live Laravel calls** — no fetch to `/admin` or `/staff` for user/RBAC data.
5. **Preview flags** — `previewLoading`, `previewEmpty`, `previewError` are QA-only query params.
6. **Reference date** — validation time comparisons use `2026-07-01` baseline for stale invitation and lockout checks.

---

## Verification

Automated checks in `dashboard/tests/users.smoke.spec.ts` and `users-access.foundation.spec.ts`:

- No password/secret strings in page body
- JetPakistan brand fixed; no legacy brand strings in fixture JSON
- Protected roles marked; high-risk permissions classified
- MFA and suspended-session validation codes fire on designated fixtures

Manual QA: confirm settings integrations section shows status labels only (no credential fields); confirm user drawer shows masked location, not raw IP; confirm settings local-preview reset restores fixture values.

---

## Related documentation

- [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md) — audit privacy, IP masking, export manifest
- [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md)
- [`RBAC-PERMISSION-CATALOG.md`](./RBAC-PERMISSION-CATALOG.md)
- [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md)
- [`DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md`](./DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md)
- [`mock-data-policy.md`](./mock-data-policy.md)
