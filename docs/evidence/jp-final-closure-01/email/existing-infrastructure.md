# JetPakistan Email Infrastructure Notes

Audit date: 2026-08-29. Read-only evidence for `jp-final-closure-01`.

## Architecture summary

JetPakistan email has **three live rendering lanes**:

| Lane | Layout | Branding source | Send path |
|------|--------|-----------------|-----------|
| **JetPK universal ops** | `emails.themes.jetpakistan.layouts.base` + `universal-event` | `JetpkEmailBrandingResolver` + `CompanyEmailProfileResolver` | `JetpkOperationalEmailService` → `JetpkOperationalEventMail` |
| **Customer booking (live)** | `emails.layouts.universal` | `CompanyEmailProfileResolver` via `BookingEmailPayloadFactory` | `BookingCommunicationService` → `BookingUniversalNotification` |
| **Modern customer/auth/manual** | `emails.layouts.modern` (htmlString) | `CompanyEmailProfileResolver` via renderers | Dedicated Mailables + renderer classes |

Non-JetPK clients still use `emails.layouts.modern` for operational mail via `OtaOperationalNotificationMail`.

---

## JetpkEmailBrandingResolver

**Role:** Resolves the `$emailBrand` array for the JetPK universal shell (logo, colors, support contacts, footer).

**Resolution order:** ClientProfile DB → `config/jetpk_email.brand` → safe JetPK constants (never Master).

**Keep:** Yes — canonical JetPK email branding for universal shell.

**Consolidate:** Overlaps with `SafeBrandingResolver` and seed defaults in `JetpkEmailBrandingResolver::$defaults`. Long-term, agency `CompanyEmailProfileResolver` should be the single read layer; JetPK resolver should thin-wrap it for `$emailBrand` shape only.

---

## JetpkEmailViewResolver

**Role:** Maps legacy type keys → single view `emails.themes.jetpakistan.universal-event` via `JetpkEmailEventTypeMap`.

**Keep:** Yes — compatibility shim for type-based preview callers (`ota:jetpk-email-preview --type=`).

**Consolidate:** Eventually fold into `JetpkEmailEventContentRegistry` (event-key-first API only).

---

## JetpkEmailSampleData / JetpkEmailSampleDataProvider

**Role:** Fake preview payloads for audit/preview commands only. No DB, no send.

**Keep:** Yes — required by `jetpk:email-preview`, `ota:jetpk-email-preview`, coverage audit.

**Consolidate:** Merge with `EmailTemplateSampleData` under one preview-data provider to avoid duplicate fixture phones/emails.

---

## universal-event.blade.php

**Role:** Single content view for all JetPK operational events. Includes conditional blocks (OTP, booking summary, invoice, agent application, etc.) driven by `JetpkEmailEventContentRegistry`.

**Keep:** Yes — core of JetPK universal template strategy.

**Consolidate:** Do not fork per-event Blade files; extend block partials only.

---

## layouts/base.blade.php (JetPK shell)

**Role:** Table-based, email-safe outer shell (640px). Inlines brand colors from `$emailBrand`. Includes header/footer partials.

**Keep:** Yes — canonical JetPK shell.

**Consolidate:** `emails.layouts.modern` and `emails.layouts.universal` remain separate for non-universal-ops paths. Goal: customer booking lane should migrate to JetPK shell when `BookingUniversalNotification` is retired.

---

## CompanyEmailProfileResolver

**Role:** Platform-wide company/email identity from default agency settings + `ota-client`/`ota-brand` config. Used by all modern renderers, operational variable merge, and admin template preview.

**Keep:** Yes — primary live-send branding source.

**Consolidate:** `JetpkEmailBrandingResolver` should delegate to this (plus ClientProfile for JetPK-specific assets) instead of maintaining parallel phone/email defaults.

---

## Preview / audit commands

| Command | Purpose |
|---------|---------|
| `jetpk:email-preview --event= --role=` | Render one JetPK ops email to `storage/app/email-previews/jetpk/` |
| `jetpk:email-coverage-audit` | Read-only ops coverage matrix + branding leakage scan |
| `jetpk:email-template-architecture-audit` | Shell/registry coverage via `JetpkPhase9hDAuditService` |
| `ota:jetpk-email-preview --type=` / `--all` | Legacy type-key previews (maps to universal view) |
| `ota:email-template-audit` | Full template catalog audit + optional HTML previews |
| `ota:email-template-smoke` | Smoke render of template registry |

**Keep:** `jetpk:*` trio for JetPK closure; `ota:jetpk-email-preview` until type-key callers removed.

**Consolidate:** Single preview CLI with `--event` and deprecated `--type` alias.

---

## Legacy / removal candidates

- **6 Mailables** (`BookingRequestReceivedMail`, etc.): superseded by `BookingUniversalNotification` in `BookingCommunicationService`; only referenced in tests.
- **7 orphaned Blade views** under `emails/bookings/*`, `emails/auth/*`, `emails/marketing/*`: no app references; markdown (`x-mail::message`) era.
- **`app/Notifications`**: directory absent; Laravel `VerifyEmail` / `ResetPassword` used directly.
- **Dual layout stack**: `modern` + `universal` + `jetpakistan` — target state is JetPK shell for all customer + ops mail.

---

## Recommended consolidation order

1. Route `JetpkEmailBrandingResolver` through `CompanyEmailProfileResolver` + `ClientProfileResolver` (remove duplicate phone/email constants).
2. Migrate `BookingUniversalNotification` to JetPK universal shell (retire `emails.layouts.universal`).
3. Delete orphaned markdown Blade views and legacy Mailables after test migration.
4. Unify preview sample data (`JetpkEmailSampleData` + `EmailTemplateSampleData`).
5. Collapse preview commands under `jetpk:email-preview`.
