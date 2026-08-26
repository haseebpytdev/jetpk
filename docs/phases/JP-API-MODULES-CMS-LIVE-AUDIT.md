# JP-API-MODULES-CMS-LIVE-AUDIT

## Phase

`JP-API-MODULES-CMS-LIVE-AUDIT` — unify Admin API configuration into **API & Modules**, safe SMTP migration, Al-Haider manual-token surface, CMS field truth remediation.

## Branch

`phase/jp-bo-04g-progressive`

## Starting SHA

`837d50d36ed5f5a03cccd60a6bdaa0ad1cab3f35`

## Engineering SHA

`d3bedc151997b9e8eabb058f9270cf18bdb0a1fd`

## Objective

Close Admin integration-management and CMS truth gaps before Group Ticketing booking flows.

## Included scope

- Replace Integrations nav with **API & Modules**
- Configured-connections-only landing
- Working Add Connection modal (provider catalog → form → save)
- Environment-aware endpoint defaults (Sabre / IATI / Al-Haider / Duffel from existing sources)
- Sabre GDS/NDC true switch toggles with independent persistence
- Al-Haider `manual_token` UI; token generation remains disabled (`ALHAIDER_TOKEN_GENERATION_ENABLED` default false)
- SMTP provider + idempotent ENV import + DB-first / ENV fallback mail resolver
- CMS homepage field remediation against current Next theme
- Backend + Playwright coverage

## Excluded scope

- Group Ticketing reservation/booking/order flows
- Al-Haider token issuance (hard zero)
- Production ENV deletion for mail
- Blind copy of legacy OTA visual design

## Investigation findings

### Add Connection root cause

1. Landing page was an IntegrationRegistry **provider catalog**, not configured connections.
2. Add Integration wizard had empty steps and after create set `openCreatePanel=false`, so credentials never opened.
3. Create UI was a `hidden` section inside an overflow drawer (easy to miss / appear nonfunctional).

### Legacy comparison (read-only `C:\Users\khadi\ota`)

| Topic | LEGACY | CURRENT JETPK (after) | KEEP/IMPROVE/REJECT |
|---|---|---|---|
| Configured connection cards | One card per SupplierConnection | Same model on API & Modules | KEEP |
| Enable toggle | Per-connection switch | Per-connection SwitchToggle | IMPROVE (accessibility) |
| Provider catalog | On Add flow | Under Add Connection modal only | KEEP concept |
| Env endpoint defaults | Sabre/IATI auto | ProviderEndpointDefaults + normalizers | KEEP / IMPROVE |
| Sabre GDS/NDC | Advanced modal switches | Capabilities SwitchToggles | IMPROVE |
| Masked secrets | Blank keep | Blank keep | KEEP |
| Legacy styling | Blade JP cards | Dashboard design system | REJECT wholesale copy |

### Al-Haider User Pass classification

**A** — username/password used only for `/api/login` token minting (`AlHaiderClient::performLogin`). Runtime requests use Bearer token. Manual-token mode strips username/password and must not call login.

### SMTP migration result

- Discover: Laravel `config/mail.php` ENV-backed SMTP remains intact.
- Model: `SupplierProvider::Smtp` connection with encrypted credentials.
- Import: `SmtpEnvironmentImportService` creates exactly one row when ENV SMTP is configured and none exists; audit `integration.smtp_imported_from_environment` (safe fields only).
- Runtime: `SmtpMailConfigResolver` applies DB when active+valid; otherwise ENV fallback. Disable does not delete ENV.

### CMS field remediation summary

| Change | Reason |
|---|---|
| Removed hero CTA label/target | Not in `HomepagePublicContentPresenter` / PublicHero |
| Added hero `search_visible` | Consumed by public hero |
| Feature board: enabled + items only | Theme ignores eyebrow/title/subtitle |
| Support: hide generic section CTA | Public uses call/chat fields |
| Switch-style enabled controls | Clearer truth toggles |

## Provider endpoint source truth

| Provider | Source |
|---|---|
| Sabre CERT | `SabreSupplierConnectionNormalizer::CERT_BASE_URL` |
| Sabre LIVE | `SabreSupplierConnectionNormalizer::LIVE_BASE_URL` |
| IATI | `IatiSupplierConnectionNormalizer` flight bases |
| Al-Haider | `config('suppliers.al_haider.default_base_url')` → `https://alhaidertravel.pk` |
| Duffel | `config('suppliers.duffel.default_base_url')` |
| SMTP | N/A (host/port credentials) |

## Tests executed

- `php artisan test --filter=ApiModulesSmtpAndConnectionsTest` — 6 passed
- `php artisan test --filter=BackOfficeSessionContractTest` — 12 passed
- `php artisan test --filter=AlHaiderManualTokenConnectionTest` — 6 passed
- `php artisan test --filter=JpInt01IntegrationHubTest` — 17 passed
- Playwright: `dashboard/tests/jp-api-modules-cms.spec.ts` (run in CI/dashboard pipeline)

## Exact files changed (engineering)

See engineering commit file list. Key paths:

- `dashboard/features/integrations/integrations-workspace.tsx`
- `dashboard/features/settings/components/api-connections-workspace.tsx`
- `dashboard/features/api-connections/components/connection-card.tsx`
- `dashboard/components/ui/switch-toggle.tsx`
- `dashboard/features/cms/components/homepage-settings-panel.tsx`
- `app/Http/Controllers/Admin/SupplierConnectionController.php`
- `app/Services/Integrations/Smtp*.php`
- `app/Support/Suppliers/ProviderEndpointDefaults.php`
- `app/Enums/SupplierProvider.php` (+ Smtp)
- `config/supplier_credentials.php`

## Migrations

None.

## Production deploy / live CMS matrices

- Backup: `jp-api-modules-cms-20260826T160238Z`
- Deployed runtime SHA: `d3bedc151997b9e8eabb058f9270cf18bdb0a1fd`
- New dashboard build: `1wjGmmUpLjOVyY0CllBUo`
- Public build unchanged: `N2UgmUu_xxKIyYUu2pLRo`
- `LIVE_SOURCE_DRIFT=0`

Live evidence (`tmp/evidence/jp-api-modules-cms/`):

| Gate | Result |
|---|---|
| API & Modules hub | PASS |
| Unconfigured provider cards | 0 |
| Sabre cards | 2 |
| SMTP card | PASS |
| Add Connection + Al-Haider endpoint/manual mode | PASS |
| CMS eyebrow save → public render → restore | PASS / PASS / PASS |
| Al-Haider token generation calls | 0 |
| Owner Al-Haider secret entry | NOT_ENTERED (stopped before secrets) |
| CMS media matrix | NOT_RUN this pass (text path proven; media deferred to follow-up) |

## Final status

Engineering deployed and live API & Modules + representative CMS text truth verified. Owner-assisted Al-Haider token entry and full CMS media matrix remain next.
