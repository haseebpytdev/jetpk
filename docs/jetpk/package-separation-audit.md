# JetPK package separation audit

**Question:** Can JetPK deploy with only common backend + JetPK UI + profile — without exposing other client branding/files?

**Answer:** **Yes, with documented boundaries.** The codebase is monorepo-style; separation is by **path convention + env**, not separate repositories (yet).

## Deploy bundle (JetPK-only UI)

| Include | Path |
|---------|------|
| Theme views | `resources/views/themes/frontend/jetpakistan/**` |
| Components | `resources/views/components/jp/**` |
| Theme assets | `public/themes/frontend/jetpakistan/**` |
| Client assets | `public/client-assets/jetpk-assets/**` |
| Ops metadata | `clients/jetpk/**` |
| Provisioner | `JetPakistanClientProfileProvisioner.php`, seed command |

## Shared backend (required — not client-branded)

Full Laravel app: `app/`, `config/`, `routes/`, `database/`, shared `resources/views/` for booking/supplier logic, `public/css/`, `public/js/`.

See [common-backend-inventory.md](common-backend-inventory.md).

## Must NOT expose on JetPK-only server

| Exclude | Reason |
|---------|--------|
| `clients/haseeb-master/` (if created) | Other client ops |
| `public/client-assets/{other-slug}/` | Other client logos/branding |
| Other client DB profiles | Use JetPK-only database or single `jetpk` profile |
| Master preview at `/haseeb-master/*` | N/A if `OTA_CLIENT_SLUG=jetpk` on root |

## Optional trim (after public UI complete)

When all JetPK pages have themed overrides:

- `resources/views/themes/frontend/v1-classic/` — fallback only
- Unused theme folders for other clients
- `Binham/` design references — never deploy

## Runtime isolation mechanisms

| Mechanism | Effect |
|-----------|--------|
| `OTA_CLIENT_SLUG=jetpk` | Default profile is JetPakistan |
| `OTA_ACTIVE_THEME=jetpakistan` | `client_layout()` / `client_view()` resolve JP theme |
| `OTA_PUBLIC_ASSET_PROFILE=jetpk-assets` | Logo/favicon URLs |
| `CurrentClientContext` | Preview vs default mode |
| `ClientPreviewLayoutBranding` | Branding override on preview routes |

On dedicated JetPK domain, root `/` serves JetPK (not master). Preview prefix `/jetpk/` optional.

## Shared view leakage (current gap)

Until Work order 1 completes, these pages render **Master Blade content** inside **JetPK shell**:

- Flight results/details/booking
- Group ticketing
- CMS pages
- Agent registration forms

This is **safe for QA** (no other client branding) but **not** fully JetPK-branded content. Not a security leak — a UI completeness gap.

## POST / mutating routes

Client parity registers **GET/HEAD only** (209 routes). Booking POST actions use unprefixed routes — unchanged by design. JetPK production uses same backend endpoints.

## Audit result

| Criterion | Status |
|-----------|--------|
| Separate theme views | Partial (19 files) |
| Separate public CSS/JS theme | Yes |
| Separate client assets profile | Scaffolded |
| Separate ops folder | Yes (`clients/jetpk/`) |
| No other client assets required | Yes, when `jetpk-assets` uploaded |
| Backend shared safely | Yes |
| Other client UI files required at runtime | No (Master views are platform default, not another client) |
| Standalone deploy documented | Yes — `docs/jetpk/*` |

## Recommended production topology

```
jetpakistan.com
├── Laravel app (shared codebase)
├── OTA_CLIENT_SLUG=jetpk
├── OTA_ACTIVE_THEME=jetpakistan
├── public_html/themes/frontend/jetpakistan/
├── public_html/client-assets/jetpk-assets/
└── No haseeb-master branding on disk
```

## Re-audit trigger

Re-run this audit when:

- Flight/booking themed views land
- JetPK dashboard themes added
- Client plans / module entitlements wired in Dev CP
