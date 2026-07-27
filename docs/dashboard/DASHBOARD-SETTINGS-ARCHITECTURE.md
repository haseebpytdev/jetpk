# Dashboard Settings Architecture — JETPK-DASH-10 Prompt 02

Phase: **JETPK-DASH-10 Prompt 02**

The JetPakistan dashboard preview defines a **fixture-backed settings domain** for organization metadata, security policy, notification routing, and integration readiness. This layer models how Laravel will eventually expose and enforce settings; it does **not** authenticate users, persist changes, or store credentials.

| Layer | Location | Role |
|-------|----------|------|
| Production settings | Laravel `/admin/settings` | Authoritative config, secrets in env/DB |
| Preview UI | `/testdash/settings/*` | Read-only contracts + local-preview editing |
| Validation | `dashboard/lib/access-control/settings-validation.ts` | Pure validation rules |
| Contracts | `dashboard/lib/access-control/settings-contracts.ts` | Read-only field definitions (Prompt 01) |
| Fixtures | `dashboard/mocks/settings-fixtures.ts` | Deterministic settings payloads |
| Service | `dashboard/services/settings-service.ts` | Fixture reads via `useMockData()` guard |

Reference date for fixtures: `SETTINGS_FIXTURE_REVISION = 2026-07-01T00:00:00.000Z`.

---

## Settings route map

All routes are prefixed by `basePath: /testdash` in production build.

| Route | Section key | Page file | Workspace component | Prompt 02 status |
|-------|-------------|-----------|-------------------|------------------|
| `/testdash/settings` | `overview` | `dashboard/app/settings/page.tsx` | `SettingsOverview` | Live |
| `/testdash/settings/general` | `general` | `dashboard/app/settings/general/page.tsx` | `GeneralSettingsWorkspace` | Live |
| `/testdash/settings/security` | `security` | `dashboard/app/settings/security/page.tsx` | `SecuritySettingsWorkspace` | Live |
| `/testdash/settings/notifications` | `notifications` | `dashboard/app/settings/notifications/page.tsx` | `NotificationSettingsWorkspace` | Live |
| `/testdash/settings/integrations` | `integrations` | `dashboard/app/settings/integrations/page.tsx` | `IntegrationSettingsWorkspace` | Live |

**Shell:** `dashboard/features/settings/settings-module-shell.tsx` — sub-nav, breadcrumbs, loading/error states.

**Server entry:** `dashboard/features/settings/settings-page-content.tsx` → `parseSettingsQuery()` → `getSettingsModule()`.

**Nav registration:** `dashboard/lib/nav-config.ts` under **Insights & system** → Settings.

**Query parameters** (`dashboard/lib/settings-query.ts`):

| Param | Purpose |
|-------|---------|
| `validationState` | Filter validation summary (`all`, `valid`, `warning`, `blocked`) |
| `previewError`, `previewLoading`, `previewEmpty` | QA simulation flags |
| `state`, `tab`, `preview` | Reserved for future tab/state routing |

---

## General settings contract

**Type:** `GeneralSettingsValues` in `dashboard/types/settings-module.ts`

**Fixture:** `FIXTURE_GENERAL_SETTINGS` in `dashboard/mocks/settings-fixtures.ts`

| Field | Fixture value | Notes |
|-------|---------------|-------|
| `organizationDisplayName` | `JetPakistan` | Must remain JetPakistan branding |
| `publicSupportLabel` | `JetPakistan Support` | Public-facing label |
| `supportEmail` | `support@jetpakistan.example` | Preview domain only |
| `supportPhone` | `+92 21 111 000 000` | Operational metadata |
| `timezone` | `Asia/Karachi` | Supported: Karachi, UTC, Dubai, London |
| `defaultCurrency` | `PKR` | Supported: PKR, USD, AED, GBP |
| `locale` | `en-PK` | Supported: en-PK, en-US, en-GB |
| `dateFormat` | `DD MMM YYYY` | Supported formats in validation |
| `operationalReferenceLabel` | `JP-OPS` | Internal reference label |
| `dashboardPaginationDefault` | `20` | UI default page size metadata |
| `reportingReferenceMetadata` | `JetPakistan OTA reporting baseline` | Reporting baseline label |

**Legacy contract:** `SETTINGS_CATEGORIES[general]` in `settings-contracts.ts` (7 read-only fields) — retained for catalog alignment; workspace uses the richer fixture type.

**UI:** `GeneralSettingsWorkspace` with `SettingsLocalPreviewForm` (11 editable preview fields).

---

## Security policy contract

**Type:** `SecuritySettingsValues` in `dashboard/types/settings-module.ts`

**Fixture:** `FIXTURE_SECURITY_SETTINGS` in `dashboard/mocks/settings-fixtures.ts`

| Field | Fixture value | Notes |
|-------|---------------|-------|
| `mfaRequirementPolicy` | `required_for_admin` | Policy label only — no MFA secrets |
| `privilegedRoleMfaPolicy` | `required` | Privileged-role MFA requirement |
| `passwordMinLength` | `12` | Policy metadata — not user passwords |
| `passwordComplexityPolicy` | `upper_lower_digit_special` | Complexity rule label |
| `sessionDurationHours` | `8` | Session lifetime metadata |
| `idleTimeoutMinutes` | `30` | Idle timeout metadata |
| `failedLoginThreshold` | `5` | Lockout trigger count |
| `lockoutDurationMinutes` | `30` | Lockout duration |
| `invitationExpiryDays` | `14` | Invitation TTL metadata |
| `highRiskApprovalPolicy` | `enabled` | Aligns with RBAC approval gates |
| `auditRetentionDays` | `365` | Audit retention metadata |
| `sessionConcurrencyPolicy` | `single_primary` | Session concurrency rule |

**Legacy contract:** `SETTINGS_CATEGORIES[security]` in `settings-contracts.ts` (6 read-only fields).

**UI:** `SecuritySettingsWorkspace` with 12 preview fields. No password reset, MFA enrollment, or secret display.

---

## Notification contract

**Type:** `NotificationSettingsValues` with `NotificationCategoryConfig[]`

**Fixture:** `FIXTURE_NOTIFICATION_SETTINGS` — **9 notification categories**:

| Key | Label | Email | Dashboard | Delivery |
|-----|-------|-------|-----------|----------|
| `booking` | Booking | ✓ | ✓ | immediate |
| `payment` | Payment | ✓ | ✓ | immediate |
| `pnr` | PNR/order | — | ✓ | immediate |
| `ticketing` | Ticketing | ✓ | ✓ | immediate |
| `supplier` | Supplier | — | ✓ | digest |
| `cmsReview` | CMS review | ✓ | ✓ | immediate |
| `userSecurity` | User security | ✓ | ✓ | immediate |
| `audit` | Audit | — | ✓ | digest |
| `systemHealth` | System health | ✓ | ✓ | immediate |

Each category includes: `enabled`, `emailChannel`, `dashboardChannel`, `severityThreshold`, `recipientRoles[]`, `deliveryMode`.

**Legacy contract:** `SETTINGS_CATEGORIES[notifications]` in `settings-contracts.ts` (7 boolean alert toggles).

**UI:** `NotificationSettingsWorkspace` — category selector with per-category preview editing and recipient role display.

---

## Integration metadata contract

**Type:** `IntegrationSettingsValues` with `IntegrationRecord[]`

**Fixture:** `FIXTURE_INTEGRATION_SETTINGS` — **8 integration records**:

| Key | Display name | Enabled | Environment | Completeness |
|-----|--------------|---------|-------------|--------------|
| `sabreGds` | Sabre GDS | ✓ | preview | 85% |
| `sabreNdc` | Sabre NDC | ✓ | preview | 80% |
| `oneApi` | One API | ✓ | preview | 75% |
| `emailDelivery` | Email delivery | ✓ | preview | 60% |
| `paymentProvider` | Payment provider | — | not_configured | 20% |
| `cmsDelivery` | CMS delivery/API | ✓ | preview | 90% |
| `auditPersistence` | Audit persistence | ✓ | preview | 50% |
| `webhookReadiness` | Webhook readiness | — | not_configured | 10% |

Each record includes: `readinessStatus`, `lastSyntheticCheck`, `capabilitySummary`, `warningState`, `futureOwner`, `documentationReference`. **No credentials, API keys, PCC, LNIATA, or SMTP secrets.**

**Legacy contract:** `SETTINGS_CATEGORIES[integrations]` in `settings-contracts.ts` (7 status metadata fields).

**UI:** `IntegrationSettingsWorkspace` — integration cards with readiness badges plus per-integration local preview form.

---

## Local-preview behavior

Settings sections support **client-side local preview editing** via `SettingsLocalPreviewForm` (`dashboard/features/settings/components/settings-local-preview-form.tsx`):

1. **Baseline** — fixture values loaded by `getSettingsModule()` from `settings-fixtures.ts`
2. **Draft** — user edits held in React state (`previewValues`)
3. **Apply preview** — copies draft into preview state; runs validation against active values
4. **Reset** — clears preview state; restores fixture baseline on refresh

| Behavior | Implemented | Future Laravel |
|----------|-------------|----------------|
| Edit fields in browser | ✓ (client state) | Server PATCH with CSRF |
| Persist across refresh | ✗ | ✓ |
| Save to database | ✗ | ✓ |
| Audit on change | ✗ (synthetic fixtures only) | ✓ |

**Settings audit events (preview fixtures):** Settings actions appear in the audit timeline as synthetic events — see [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md).

| Event type | Trigger (preview) | Target |
|------------|---------------------|--------|
| `settings.viewed` | Section workspace load | `target.type: setting`, id = section key |
| `settings.generalPreviewChanged` | General local preview apply | `general` |
| `settings.securityPreviewViewed` | Security workspace open | `security` |
| `settings.notificationPreviewChanged` | Notification preview apply | `notifications` |
| `settings.integrationMetadataViewed` | Integrations workspace open | `integrations` |

All carry `metadata.previewOnly: true` and TEST-NET masked IPs. Live Laravel PATCH (Phase C) will emit real events without the preview flag.

Copy in every workspace: *"Local preview editing — changes are not persisted. Refresh restores fixture values."*

`settings.update` permission (`isHighRisk: true`) is catalogued but **not enforced** in preview — forms are intentionally non-persistent regardless of simulated permission state.

---

## Validation

Pure functions in `dashboard/lib/access-control/settings-validation.ts`:

| Function | Target | Notable rules |
|----------|--------|---------------|
| `validateGeneralSettings()` | General | Missing org label, invalid email/phone, unsupported timezone/currency/locale/date format |
| `validateSecuritySettings()` | Security | Weak password length (<10 warning), privileged MFA disabled, excessive session (>24h), high login threshold (>10), missing lockout, disabled high-risk approval, missing audit retention |
| `validateNotificationSettings()` | Notifications | Enabled category without channel, no recipient roles, digest without email, security/audit alerts disabled |
| `validateIntegrationSettings()` | Integrations | Enabled but incomplete (<50%), readiness conflict, missing capability summary, unsupported environment, **sensitive key pattern** (`SECRET_PATTERN`) |
| `validateAllSettings()` | All sections | Aggregates all section validators |

Issues use the shared `AccessValidationIssue` shape (`severity`, `code`, `fieldPath`, `blocking`, `suggestedResolution`).

**Overview readiness:** `getSettingsModule()` computes per-section `ready | warning | incomplete` states for `SettingsOverview`.

**UI:** `SettingsValidationSummary` in each workspace; filterable via `?validationState=`.

---

## Protected fields

Settings preview **must never** expose or accept:

| Category | Prohibited |
|----------|------------|
| Authentication secrets | Passwords, hashes, MFA seeds, backup codes, recovery tokens |
| API credentials | Sabre PCC/LNIATA, NDC keys, bearer tokens, webhook signing secrets |
| Payment capture | Card numbers, CVV, gateway secrets |
| Email transport | SMTP passwords, API keys |
| Session internals | Session IDs, CSRF tokens, cookie values |

**Enforcement:**

- All `settings-contracts.ts` fields have `sensitive: false`; secrets excluded entirely
- `validateIntegrationSettings()` blocks `SECRET_PATTERN` matches in integration keys/summaries
- Integration `capabilitySummary` explicitly states "no SMTP credentials in preview" where relevant
- Smoke tests reject password/secret strings in DOM

**Allowed metadata:** policy labels, numeric thresholds, boolean toggles, readiness status strings, environment labels, completeness percentages.

---

## Server-authoritative boundary

| Concern | Preview (Prompt 02) | Laravel (future) |
|---------|---------------------|------------------|
| Settings source | `settings-fixtures.ts` | Database / config store |
| Who can view | Open `/testdash` | `settings.view` policy |
| Who can update | Nobody (non-persistent) | `settings.update` policy |
| Validation | Client-side only | Server-side on PATCH |
| Credential storage | Not present | Encrypted env/DB, never in JSON API |
| Effective policy | Display metadata | Enforced on login, session, MFA |

`useMockData()` guard in `settings-service.ts` throws `SettingsServiceError` (`SET-PREVIEW-NO-LIVE`) when live mode is requested.

Laravel remains authoritative for MFA enforcement, password policy application, session timeout, lockout, and integration credential management.

---

## Future Laravel settings API

Proposed endpoints (names illustrative; implement under authenticated admin group):

| Endpoint | Permission gate | Response DTO |
|----------|-----------------|--------------|
| `GET /api/dashboard/settings` | `settings.view` | Overview metrics + category readiness |
| `GET /api/dashboard/settings/general` | `settings.view` | `GeneralSettingsValues` (no secrets) |
| `GET /api/dashboard/settings/security` | `settings.view` | `SecuritySettingsValues` (policy metadata only) |
| `GET /api/dashboard/settings/notifications` | `settings.view` | `NotificationSettingsValues` |
| `GET /api/dashboard/settings/integrations` | `settings.view` | `IntegrationSettingsValues` (status only) |
| `PATCH /api/dashboard/settings/{section}` | `settings.update` | Updated section DTO + validation issues |

**Service swap:** `getSettingsModule()` reads Laravel when `useMockData()` is false; types in `dashboard/types/settings-module.ts` are the contract.

**Mapping:** Laravel admin settings controllers should serialize to the same field keys as fixtures. Credential fields remain server-side only — never in dashboard JSON.

---

## Persistence roadmap

### Phase A — Read-only API (aligns with auth roadmap Phase A)

1. `GET` endpoints per settings section
2. Session cookie forwarding from Next server components
3. `settings.view` policy gate
4. Overview metrics computed server-side

### Phase B — Local preview parity (current preview state)

1. Dashboard UI matches fixture contracts ✓ (Prompt 02)
2. Validation rules shared between TS contracts and Laravel FormRequest ✓ (TS side done)
3. Integration readiness from supplier connection status (not credentials)

### Phase C — Controlled mutations

1. `PATCH` per section with CSRF
2. `settings.update` policy + audit entry per change
3. High-risk security policy changes require confirmation
4. Integration enable/disable updates readiness metadata only — credential rotation stays in Laravel admin

### Phase D — Notification delivery

1. Notification category config drives Laravel notification channels
2. Recipient role groups resolved server-side
3. No email addresses or webhook URLs in dashboard JSON

---

## Explicit non-goals

The settings preview **must not**:

1. Store or display supplier credentials (Sabre, NDC, One API, payment gateway)
2. Expose SMTP, webhook, or API signing secrets
3. Allow password changes or MFA enrollment from the dashboard
4. Persist local-preview edits to fixtures, localStorage, or Laravel
5. Replace Laravel admin settings pages for credential management
6. Send real notification emails or webhooks from preview edits
7. Export settings as a downloadable file with secrets
8. Bypass `SettingPolicy::update` when live integration is enabled
9. Merge staff and admin settings scopes into one URL space in production
10. Introduce Spatie or parallel settings packages in the dashboard

---

## Frontend architecture

```text
SettingsPageContent (server)
  → parseSettingsQuery() / getSettingsModule()
  → SettingsModuleShell
      → sub-nav: Overview | General | Security | Notifications | Integrations
      → SettingsOverview                    [overview]
      → GeneralSettingsWorkspace            [general]
      → SecuritySettingsWorkspace           [security]
      → NotificationSettingsWorkspace       [notifications]
      → IntegrationSettingsWorkspace        [integrations]
          → SettingsValidationSummary
          → SettingsLocalPreviewForm (client preview only)
```

Supporting libraries:

- `lib/settings-query.ts` — URL query parsing
- `lib/access-control/settings-contracts.ts` — read-only catalog fields
- `lib/access-control/settings-validation.ts` — validation rules
- `mocks/settings-fixtures.ts` — fixture payloads + clone helpers
- `services/settings-service.ts` — mock guard + module assembly

---

## Related documentation

- [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md) — audit event model and settings event cross-links
- [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md) — RBAC domain (roles, permissions)
- [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md) — protected fields and privacy rules
- [`RBAC-PERMISSION-CATALOG.md`](./RBAC-PERMISSION-CATALOG.md) — `settings.view` / `settings.update` permissions
- [`DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md`](./DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md) — migration sequence
