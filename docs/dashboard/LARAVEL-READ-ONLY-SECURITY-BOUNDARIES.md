# Laravel Read-Only Security Boundaries — JETPK-DASH-11

## Scope

Security rules for dashboard read-only Laravel integration. Applies to API serializers, frontend adapters, and UI rendering.

## Prohibited response fields

Future Laravel read-only responses and frontend transforms **must never expose:**

| Category | Fields (examples) |
|----------|-------------------|
| Authentication | passwords, password hashes, MFA secrets, recovery codes |
| Session | auth cookies, session IDs, CSRF tokens |
| API access | API keys, supplier credentials |
| GDS | PCC, LNIATA |
| Payments | raw card numbers, PAN, CVV |
| Identity | passport numbers, national ID values |
| Audit | unrestricted raw metadata, full user-agent blobs |
| Errors | stack traces, SQL, file paths, internal exception classes |

Implementation reference: `dashboard/lib/read-only/sensitive-fields.ts` → `SENSITIVE_FIELD_KEYS`.

## Safe identifiers only

When identifiers are required in UI:

- Application IDs: `JP-BKG-0001`, `JP-USR-0042`
- Safe correlation: `requestIdSafe` (support-facing, non-sensitive)
- Public references: booking reference, PNR locator (operational need)

## Server boundary

1. Laravel API resources / serializers strip sensitive keys.
2. `stripSensitiveFields()` used in frontend adapters as defense-in-depth.
3. `containsSensitiveKeys()` available for test assertions.

## Error sanitization

`sanitizeErrorMessage()` blocks patterns matching SQL, paths, passwords, tokens, PCC, LNIATA.

UI shows `referenceIdSafe` only — never raw server request IDs.

## Authentication storage

| Storage | Allowed |
|---------|---------|
| httpOnly Laravel session cookie | Yes (browser managed) |
| localStorage | **No** |
| sessionStorage | **No** |
| Client-visible session IDs | **No** |

Verified: `read-only-integration.foundation.spec.ts` → no token storage.

## RBAC

- Authorization enforced server-side (Laravel policies / `StaffPermission`).
- Dashboard UI reflects permissions; does not grant access client-side.
- Laravel read-only RBAC endpoints (`users`, `roles`, `permissions`, `rbac/matrix`) enforce `users.view`, `roles.view`, and `permissions.view` server-side; dashboard UI mirrors assignments only.

## GDS / NDC

- Channel must remain explicit (`Sabre GDS` vs `Sabre NDC`).
- No merging of GDS PNR and NDC order metrics without labels.
- Supplier credentials never in read responses.

## Audit privacy

- Actor display names and safe emails only.
- IP addresses: TEST-NET in fixtures; production: masked or omitted per policy.
- Event payloads: operational summary only.

## Fixture vs live

- Fixture data labeled `FixtureDataNotice`.
- Live read-only labeled `LiveReadOnlyNotice`.
- Failed live calls do not fall back to fixtures (prevents data leakage confusion).

## Prompt 04 status

| Item | Status |
|------|--------|
| CMS sanitized structured content | ✅ No arbitrary script/HTML in API resources |
| Users auth secrets excluded | ✅ password, MFA, tokens, sessions |
| Settings metadata only | ✅ No SMTP/API keys/PCC/LNIATA |
| Audit field masking | ✅ Masked TEST-NET ranges; no raw headers |
| RBAC matrix read-only | ✅ No assignment mutations |
| Mutation routes | ❌ None added (by design) |

## Prompt 01 status

| Item | Status |
|------|--------|
| Sensitive field list | ✅ Documented + coded |
| Error sanitization | ✅ Implemented |
| No credential exposure in new code | ✅ Verified |
| Production Laravel endpoints | ❌ Not created (by design) |
| Login changes | ❌ Deferred to Prompt 02 |
