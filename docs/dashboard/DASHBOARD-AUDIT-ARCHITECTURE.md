# Dashboard Audit Architecture — JETPK-DASH-10

Phase: **JETPK-DASH-10** (settings, users, RBAC, audit foundation)

The JetPakistan dashboard preview defines a **fixture-backed audit domain** for security and compliance event review. This layer models how Laravel will eventually emit and expose audit records; it does **not** write live audit entries or persist exports.

| Layer | Location | Role |
|-------|----------|------|
| Production audit | Laravel admin/staff actions | Authoritative append-only log |
| Preview UI | `/testdash/audit` | Synthetic timeline + export preview |
| Contracts | `dashboard/types/access-control.ts`, `dashboard/types/audit.ts` | Shared event model and query DTOs |
| Fixtures | `dashboard/mocks/audit-fixtures.ts` | **60** deterministic preview events |
| Service | `dashboard/services/audit-service.ts` | Fixture reads via `useMockData()` guard |

Reference date: `AUDIT_REFERENCE_DATE = 2026-07-01T00:00:00.000Z` (aligned with user/RBAC fixtures).

---

## Audit route (`/testdash/audit`)

All routes are prefixed by `basePath: /testdash` in production build.

| Route | Page file | Shell | Status |
|-------|-----------|-------|--------|
| `/testdash/audit` | `dashboard/app/audit/page.tsx` | `AuditModuleShell` | Live |

**Nav registration:** `dashboard/lib/nav-config.ts` under **Insights & system** → Audit.

**Server entry:** `AuditPageContent` → `parseAuditQuery()` → `getAuditModule()`.

**Client workspace:** `AuditWorkspace` — summary metrics, filter bar, active filters, data table, mobile cards, event detail drawer, security panel, authorization summary, export preview.

**Permission gate (future):** `audit.view` — granted to Super Administrator, Administrator, Read-only Auditor in fixtures. Preview is open; Laravel will enforce on live API.

---

## Event model (expanded `AuditEvent`)

Types: `dashboard/types/access-control.ts` (`AuditEvent`, supporting enums) and `dashboard/types/audit.ts` (query/module DTOs).

```text
AuditEvent
├── id                    (JP-AUD-####)
├── type                  (AuditEventType — 40 catalogued types)
├── category              (AuditCategory)
├── severity              (AuditSeverity)
├── outcome               (AuditOutcome)
├── actor                 (AuditActor)
├── target                (AuditTarget)
├── actionLabel           (human-readable action)
├── summary               (one-line description)
├── occurredAt            (ISO-8601)
├── sourceModule          (dashboard module key)
├── permissionKey         (nullable — catalog key when relevant)
├── authorizationOutcome  (AuditAuthorizationOutcome)
├── roleContext           (nullable role id/label)
├── riskState             (AuditRiskState)
├── changeSummary         (nullable — preview diff text)
├── validationState       (ValidationState)
└── metadata              (AuditMetadata)
```

**Fixture IDs:** `JP-AUD-0001` through `JP-AUD-0060`. All events carry `metadata.syntheticSource: "fixture"`.

---

## Actor model

`AuditActor` describes who initiated the event:

| Field | Purpose |
|-------|---------|
| `actorType` | `dashboardUser`, `system`, `supplierChannel`, `anonymousPreview`, `scheduledProcess` |
| `userId` | Fixture user id (`JP-USR-####`) when `actorType === "dashboardUser"` |
| `displayName` | Short display name (no email in summary line) |
| `roleLabel` | Role label at time of action |
| `department` | Department label |
| `status` | User status snapshot |
| `highRiskAccess` | Flag when actor holds elevated permissions |

**Cross-links:** actor user drawer via `getAuditActorHref()` → `/users?selected=JP-USR-####`.

Actor seeds in fixtures reference the **40 fixture users**; validation rejects unknown `userId` references.

---

## Target model

`AuditTarget` identifies the resource affected:

| Field | Purpose |
|-------|---------|
| `type` | `AuditTargetType` — 17 target kinds |
| `id` | Stable entity id or section key |
| `label` | Human-readable target label |

**Target types:** `user`, `role`, `permission`, `booking`, `payment`, `customer`, `supplier`, `agent`, `pnrOrder`, `ticketDocument`, `report`, `cmsPage`, `cmsSection`, `setting`, `integration`, `auditEvent`, `dashboard`.

**Cross-links:** `getAuditTargetHref()` in `lib/audit/target-links.ts` resolves drill-through routes (e.g. role → `/users/roles?selected=`, setting → `/settings/{section}`).

---

## Categories

Ten audit categories align with dashboard modules and security domains:

| Category | Typical events |
|----------|----------------|
| `authentication` | Sign-in, MFA, lockout, invitation |
| `users` | Record viewed, role preview, security review |
| `roles` | Role viewed, comparison opened |
| `permissions` | Catalog viewed, matrix viewed, assignment preview |
| `bookings` | Booking viewed |
| `operations` | PNR, ticket, cancellation, ticketing readiness |
| `reports` | Report viewed, filter changed, export preview |
| `cms` | Page/section/banner preview, validation warning |
| `settings` | Section viewed, local preview changes |
| `security` | High-risk permission, protected role, excessive access |

Filter facet: `?category={value}`.

---

## Severity

Four levels, ordered for sort priority (critical first):

| Severity | Use |
|----------|-----|
| `info` | Routine read/preview actions |
| `notice` | Notable but non-blocking activity |
| `warning` | Policy concern or denied preview |
| `critical` | Lockout, excessive access, unsafe validation |

Filter facet: `?severity={value}`.

---

## Outcomes

| Outcome | Meaning |
|---------|---------|
| `success` | Action completed (preview or simulated allow) |
| `failure` | Denied or failed attempt |
| `partial` | Incomplete or mixed result |
| `preview` | Synthetic preview-only action — no live mutation |

Most fixture events use `preview` or simulated `failure` for security scenarios. Filter facet: `?outcome={value}`.

---

## Authorization events

Each event may include authorization context:

| Field | Values |
|-------|--------|
| `permissionKey` | Catalog permission key (e.g. `users.view`, `audit.export`) or `null` |
| `authorizationOutcome` | `allowed`, `denied`, `requiresApproval`, `unavailable`, `notApplicable` |
| `roleContext` | Role id/label under which decision was evaluated |

**UI:** `AuditAuthorizationSummary` in event detail drawer explains allow/deny/requires-approval state.

**Security filter:** `?authorization=denied` plus `?securityView=1` narrows to security-relevant events (see Controlled states).

Authorization preview events (e.g. `permission.authorizationPreviewEvaluated`) demonstrate fixture-only policy simulation — not Laravel enforcement.

---

## Preview-only events

All **60 fixture events** set `metadata.previewOnly: true`. Summaries use explicit preview wording (e.g. *"Preview-only role preview opened"*).

**Catalogued preview mutation types** (must retain `previewOnly` flag):

- `user.rolePreviewApplied`
- `cms.sectionPreviewChanged`
- `settings.generalPreviewChanged`
- `settings.notificationPreviewChanged`
- `report.exportPreviewGenerated`

Validation rejects summaries matching **fake mutation patterns** (`live save`, `persisted to production`, `role mutation applied`, etc.).

**Outcome `preview`** distinguishes synthetic actions from simulated success/failure in security scenarios.

---

## Privacy rules

Audit fixtures and UI follow [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md):

- No passwords, MFA secrets, session tokens, API keys, or supplier credentials in metadata
- Actor email omitted from table summary; structured `userId` only where needed for cross-links
- No raw request payloads or payment instrument data
- Export manifest excludes prohibited columns (no email, no full IP, no secrets)

`validateAuditEvent()` scans metadata for unsafe keys (`password`, `token`, `sessionId`, `pcc`, `lniata`, etc.).

---

## IP masking (TEST-NET)

All IP metadata uses **RFC 5737 documentation ranges**:

| Prefix | Block |
|--------|-------|
| `192.0.2.` | TEST-NET-1 |
| `198.51.100.` | TEST-NET-2 |
| `203.0.113.` | TEST-NET-3 |

Fields:

- `metadata.maskedIp` — documentation-range address or `null` when unavailable
- `metadata.maskedNetworkRange` — optional CIDR label (also TEST-NET)

Validation (`TEST_NET_PREFIXES` in `audit-validation.ts`) blocks unmasked IPv4 patterns. Future Laravel integration must mask before JSON serialization.

User session summaries reuse the same masking convention (`lastSignInMaskedLocation`).

---

## Export manifest

Client-side export preview only — **not** a live `audit.export` download.

| Item | Detail |
|------|--------|
| Builder | `buildAuditExportManifest()` in `lib/audit/export-preview.ts` |
| Columns | 15 approved fields (`AUDIT_EXPORT_COLUMNS`) |
| Filename | `jetpakistan-audit-preview-{start}-to-{end}.csv` |
| Flag | `previewOnly: true` on manifest |
| CSV safety | `buildCsvContent()` + `escapeCsvCell()` — formula injection neutralized |

**Approved columns:** eventId, timestamp, category, eventType, actorDisplayName, actorType, targetLabel, targetType, sourceModule, severity, outcome, risk, channel, maskedNetworkRange, validationState.

**Excluded:** email, full IP, secrets, raw payloads. `audit.export` permission is catalogued (SA only) but **not executable** in preview.

**UI:** `AuditExportPreview` — manifest summary and row count; no file download to production storage.

---

## Validation

Pure functions in `dashboard/lib/audit/audit-validation.ts`:

| Function | Scope |
|----------|-------|
| `validateAuditEvent()` | Single event — actor/target, enums, timestamp, TEST-NET IP, actor user ref, permission key, preview markers, secret scan, fake mutation wording |
| `validateAuditCatalog()` | Full fixture set — duplicate IDs + per-event validation |
| `isSecurityAuditEvent()` | Security panel filter helper |

Notable validation codes: `AUDIT_UNMASKED_IP`, `AUDIT_SECRET_METADATA`, `AUDIT_MISSING_PREVIEW_MARKER`, `AUDIT_FAKE_MUTATION`, `AUDIT_INVALID_ACTOR_REF`.

Foundation tests: `dashboard/tests/audit-security.foundation.spec.ts` (**22** assertions).

---

## Controlled states

`getAuditModule()` returns `AuditModuleResult.state`:

| State | Trigger |
|-------|---------|
| `ready` | Normal fixture load |
| `loading` | `?previewLoading=1` (QA simulation) |
| `empty` | `?previewEmpty=1` |
| `error` | Service throw or `?previewError=1` |

Error shell: `AuditErrorShell` with reference id (`AUD-PREVIEW-SIM-ERR`, `AUD-PREVIEW-NO-LIVE`).

Live mode (`useMockData() === false`) throws `AuditServiceError` — no Laravel fallback in this phase.

---

## URL state

Parsed by `parseAuditQuery()` in `dashboard/lib/audit-query.ts`:

| Param | Purpose |
|-------|---------|
| `search`, `q` | Full-text search (id, summary, actor, target, type) |
| `category`, `eventType`, `severity`, `outcome` | Event filters |
| `actorType`, `actor`, `targetType`, `sourceModule` | Actor/target filters |
| `risk`, `authorization`, `channel` | Risk and auth filters |
| `datePreset`, `startDate`, `endDate` | Date range (`last_24_hours` … `custom`) |
| `validationState` | Validation filter |
| `securityView` | `1` — security events only |
| `page`, `pageSize`, `sort`, `direction` | Pagination and sort |
| `selected` | Event detail drawer (`JP-AUD-####`) |
| `state` | Reserved workspace state |
| `previewError`, `previewLoading`, `previewEmpty` | QA simulation |

Default date preset: **last 30 days**. Default sort: `occurredAt` desc. Page sizes: 10, 20, 50.

---

## Future Laravel audit source

When live integration is enabled (auth roadmap Phase D):

| Concern | Laravel | Dashboard |
|---------|---------|-----------|
| Event creation | Server middleware, policies, observers | Read-only consumer |
| API | `GET /api/dashboard/audit` (`audit.view`) | `getAuditModule()` swap |
| IP masking | Server-side before persist/serialize | Display as received |
| Export | `audit.export` — server CSV/JSON download | Replace export preview |
| Preview flag | Absent on real events | `previewOnly` UI badge hidden |

Event types and categories in this document are **contracts** for Laravel audit emission — align with existing admin audit patterns documented in `docs/audit/admin-rbac-matrix.md`.

Service swap: `audit-service.ts` reads Laravel when `useMockData()` is false; error code `AUD-PREVIEW-NO-LIVE`.

---

## Retention considerations

Each event carries `metadata.retentionCategory`:

| Category | Typical retention |
|----------|-------------------|
| `standard` | General operational events |
| `security` | Auth failures, lockouts, authorization denials |
| `compliance` | RBAC preview, role assignment review |
| `operational` | Module views, report filters |

Settings metadata (`auditRetentionDays: 365` in security fixtures) is **policy display only** — not enforced in preview.

Future Laravel should apply retention jobs per category; dashboard export must respect retention windows and redaction rules.

---

## Explicit non-goals

The audit preview **must not**:

1. Write audit records to Laravel, database, or filesystem
2. Execute live `audit.export` downloads
3. Store full client IPs, emails, or secrets in fixtures or export columns
4. Claim live mutations in event summaries
5. Replace Laravel as the audit authority
6. Correlate cross-tenant or production log streams
7. Provide real-time websocket ingestion
8. Bypass `audit.view` / `audit.export` policies when live integration is enabled
9. Persist filter or drawer state beyond URL query params
10. Merge admin and staff audit scopes into one production view without server filtering

---

## Frontend architecture

```text
AuditPageContent (server)
  → parseAuditQuery() / getAuditModule()
  → AuditModuleShell
      → PreviewDataBanner + preview-only notice
      → AuditWorkspace (client)
          → AuditSummaryMetrics
          → AuditFilterBar / AuditActiveFilters
          → AuditDataTable + AuditMobileCard + Pagination
          → AuditSecurityPanel              [securityView]
          → AuditEventDetailDrawer          [selected]
              → AuditAuthorizationSummary
              → target/actor cross-links
          → AuditExportPreview              [manifest only]
```

**Key files:**

| Component / lib | Path |
|-----------------|------|
| Fixtures | `mocks/audit-fixtures.ts` |
| Query parsing | `lib/audit-query.ts` |
| Filters / sort | `lib/audit/query-filters.ts` |
| Date presets | `lib/audit/date-presets.ts` |
| Validation | `lib/audit/audit-validation.ts` |
| Export preview | `lib/audit/export-preview.ts` |
| Target links | `lib/audit/target-links.ts` |
| Service | `services/audit-service.ts` |

---

## Related documentation

- [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md) — RBAC domain and permission catalog
- [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md) — privacy and protected fields
- [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md) — settings audit event types
- [`DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md`](./DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md) — live audit API migration
- [`RBAC-PERMISSION-CATALOG.md`](./RBAC-PERMISSION-CATALOG.md) — `audit.view`, `audit.export`
