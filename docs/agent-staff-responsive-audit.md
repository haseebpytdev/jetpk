# Agent + Agent Staff responsive visual audit (local)

Audit-only sprint. Does **not** upload to production. Run against `http://127.0.0.1:8000` only.

## 1. Prerequisites (local DB)

Ensure migrations are current (required for account dropdown / profile):

```bash
php artisan migrate
```

## 2. Seed local data (manual, idempotent)

```bash
php artisan db:seed --class=ResponsiveAgentPortalAuditSeeder
```

Creates:

| Entity | Details |
|--------|---------|
| Agency 1 | JetPakistan — wallet PKR 75,000, credit limit 150,000 |
| Agency 2 | EasyTicket (long name isolation) |
| Agent admin | `agent.jetpakistan@example.test` / Asif Khalil |
| Staff restricted | `staff.restricted@ota.demo` / Ali Restricted (no permissions) |
| Staff broad | `staff.full@ota.demo` / Sara Full Access (view wallet, ledger, agency, etc.; no agency edit) |
| Password | `Password123!` |

Also seeds bookings, travelers, deposits, ledger rows, and support tickets for JetPakistan.

## 3. Environment variables (local only — do not commit)

```env
LOCAL_OTA_URL=http://127.0.0.1:8000
OTA_AUDIT_AGENT_EMAIL=agent.jetpakistan@example.test
OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL=staff.restricted@ota.demo
OTA_AUDIT_AGENT_STAFF_FULL_EMAIL=staff.full@ota.demo
OTA_AUDIT_PASSWORD=Password123!
```

`playwright.responsive.agent.config.ts` sets these defaults when unset.

## 4. Run audit

Start Laravel locally, then:

```bash
npm run ui:audit:responsive:agent:chromium
```

Full browser matrix (after Chromium is acceptable):

```bash
npm run ui:audit:responsive:agent
```

## 5. Outputs

| Output | Path |
|--------|------|
| Markdown report | `UI_test/reports/agent-staff-responsive-audit.md` |
| JSON report | `UI_test/reports/agent-staff-responsive-audit.json` |
| Screenshots | `UI_test/screenshots/agent/`, `agent_staff_restricted/`, `agent_staff_full/` |
| Interactive | `UI_test/screenshots/interactive/{role}/` |

## 6. Safety

- No production SFTP upload (`uploadOnSave` stays false)
- No Sabre/payment/ticketing/API config changes
- No Blade/CSS fixes in this sprint — report findings first
