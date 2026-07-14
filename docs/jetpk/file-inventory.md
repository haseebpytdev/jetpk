# JetPK file inventory

JetPakistan-only files for standalone deploy packaging. Regenerate after major theme work.

**Profile:** slug `jetpk`, theme `jetpakistan`, asset profile `jetpk-assets`

## Theme views (`resources/views/themes/frontend/jetpakistan/`)

| Path | Purpose |
|------|---------|
| `layouts/frontend.blade.php` | Public shell |
| `layouts/auth.blade.php` | Auth shell |
| `partials/header.blade.php` | Site header |
| `partials/drawer.blade.php` | Mobile nav |
| `partials/footer.blade.php` | Site footer |
| `frontend/home.blade.php` | Homepage |
| `frontend/about.blade.php` | About |
| `frontend/support.blade.php` | Support form |
| `frontend/support/submitted.blade.php` | Support confirmation |
| `frontend/booking/lookup.blade.php` | Booking lookup |
| `sections/hero.blade.php` | Hero + search |
| `sections/feature-board.blade.php` | Feature grid |
| `sections/trust.blade.php` | Trust strip |
| `sections/groups.blade.php` | Groups CTA |
| `sections/fares.blade.php` | Featured fares |
| `sections/routes.blade.php` | Popular routes |
| `sections/destinations.blade.php` | Destinations |
| `sections/why-book.blade.php` | Why book |
| `sections/support-cta.blade.php` | Support CTA |

**Count:** 19 files

### Planned additions (Work order 1)

- `frontend/flights/results.blade.php`
- `frontend/flights/details.blade.php`
- `frontend/flights/return-options.blade.php`
- `frontend/booking/passengers.blade.php`
- `frontend/booking/review.blade.php`
- `frontend/booking/confirmation.blade.php`
- `frontend/groups/*.blade.php`
- `frontend/pages/cms.blade.php`
- Auth-specific partials (optional)

## Components (`resources/views/components/jp/`)

| File | Component tag |
|------|---------------|
| `alert.blade.php` | `<x-jp.alert>` |
| `bene-card.blade.php` | `<x-jp.bene-card>` |
| `booking-timeline.blade.php` | `<x-jp.booking-timeline>` |
| `button.blade.php` | `<x-jp.button>` |
| `card.blade.php` | `<x-jp.card>` |
| `chip.blade.php` | `<x-jp.chip>` |
| `dest-card.blade.php` | `<x-jp.dest-card>` |
| `empty-state.blade.php` | `<x-jp.empty-state>` |
| `fare-card.blade.php` | `<x-jp.fare-card>` |
| `flight-arc.blade.php` | `<x-jp.flight-arc>` |
| `form-group.blade.php` | `<x-jp.form-group>` |
| `group-card.blade.php` | `<x-jp.group-card>` |
| `icon.blade.php` | `<x-jp.icon>` |
| `input.blade.php` | `<x-jp.input>` |
| `modal.blade.php` | `<x-jp.modal>` |
| `page-hero.blade.php` | `<x-jp.page-hero>` |
| `payment-summary.blade.php` | `<x-jp.payment-summary>` |
| `result-card.blade.php` | `<x-jp.result-card>` |
| `route-card.blade.php` | `<x-jp.route-card>` |
| `table.blade.php` | `<x-jp.table>` |
| `trust-card.blade.php` | `<x-jp.trust-card>` |

## Theme public assets (`public/themes/frontend/jetpakistan/`)

```
css/tokens.css
css/theme.css
css/forms.css
css/search.css
css/search-overrides.css
js/theme.js
js/search.js
js/effects.js
js/reveal.js
```

**Count:** 9 files

## Client branding assets (`public/client-assets/jetpk-assets/`)

Expected layout (upload before production):

```
logo/logo.svg          (or .png)
favicon/favicon.ico
banners/               (optional homepage banners)
```

## Client ops metadata (`clients/jetpk/`)

```
client.json
branding.json
modules.json
deployment.json
env.production.example
notes.md
```

## Backend touchpoints (JetPK-specific, not UI)

| File | Role |
|------|------|
| `app/Services/Client/JetPakistanClientProfileProvisioner.php` | DB profile seed |
| `app/Console/Commands/OtaSeedJetPakistanClientProfileCommand.php` | CLI seed |
| `config/client_themes.php` | Registry entry `jetpakistan` |

## Controllers using `client_view()` for JetPK

| Controller | Views |
|------------|-------|
| `HomeController` | `frontend.home` |
| `SupportController` | `frontend.support`, `frontend.support.submitted`, `frontend.about` |
| `GuestBookingLookupController` | `frontend.booking.lookup` |

Expand this list as themed pages are added (flights, booking, groups).

## Not JetPK-only (shared — do not omit on deploy)

See [common-backend-inventory.md](common-backend-inventory.md).
