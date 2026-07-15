# KNOWN-LIMITATIONS — JetPakistan Mobile App Theme

## 1. Not execution-verified — the main risk

No PHP runtime, no browser, no Vite/Playwright in the authoring sandbox. This package is **five
stacked CSS layers over markup read but never rendered**. `previews/` is empty for the same reason.

**Required before shipping:** lint the 4 PHP files; then screenshot **360 / 390 / 430 / 768** with the
toggle **on and off**, prioritising **agent finance** (amounts) and the **booking flow** (fares,
baggage, review sheet). I flagged this at every phase; it is the one debt that cannot be discharged
from here.

## 2. Corrections made during the programme

| Claim | Correction |
|---|---|
| MA-0 plan: "MA-3 = customer portal" | The audit showed **15 of 51 views are the public/booking flow** — the revenue path. Resequenced to go first |
| Plan assumed 51 controller migrations | **None were needed.** The skin themes all 51; migration is now an optional enhancement path |
| MA-4 assumed one customer family | There are **two** — `.ota-mobile-customer__*` **and** hyphenated `.ota-mobile-customer-dashboard*` |

## 3. Deliberate restraint

The 8 page families carry **362 selectors**. The skin touches only shared **card / section / list /
sheet / form / button / amount** primitives — the safest, highest-leverage surface. **Expect one
visual-iteration pass**, most likely on `home` (321 lines) and `flights/results` (190). That is
anticipated, not failure.

## 4. Open items

| Item | Status |
|---|---|
| **Customer travelers mobile** | Open — recommend accepting the responsive fallback (parity §3) |
| Per-page structural overrides | None needed today; MA-1's `client_view(…,'mobile')` is wired if one arises |
| Label `for`/`id` audit of `mobile/**` | Not done — shared tree, needs the white-label decision |
| Per-tenant mobile skin | Config-level today. A `client_profiles.active_mobile_theme` column is a documented one-line upgrade in `resolvedMobileTheme()` |
| Tablets take the mobile shell at 768px | Existing behaviour (UA regex). The skin centres the frame; confirm this is wanted |
| `:has()` (once) | Current mobile browsers fine; swap for an explicit class if older WebKit matters |
| `client_theme()->frontendThemeUrl()` for tokens | Confirm it resolves under the mobile shell |

## 5. Hard constraints honoured

**Never edited:** `layouts/mobile-app`, `resources/views/mobile/**`, `public/css/ota-mobile-app.css`,
`ota-design-system.css`, `RuntimeThemeManager`. **0 shared/parwaaz files changed.** No route,
controller, model, migration, policy, permission, or financial logic touched. **Rollback is one env
var.**
