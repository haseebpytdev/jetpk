# JetPK component library status

Location: `resources/views/components/jp/`  
Styles: `public/themes/frontend/jetpakistan/css/{tokens,theme,forms,search,search-overrides}.css`

## Complete

| Component | Tag | Used in |
|-----------|-----|---------|
| Alert | `<x-jp.alert>` | Forms, validation |
| Button | `<x-jp.button>` | CTAs across pages |
| Card | `<x-jp.card>` | About, support, inner pages |
| Chip | `<x-jp.chip>` | Tags, filters |
| Form group | `<x-jp.form-group>` | Support, lookup |
| Page hero | `<x-jp.page-hero>` | About, support, lookup |
| Icon | `<x-jp.icon>` | Header, auth, cards |
| Fare card | `<x-jp.fare-card>` | Homepage fares |
| Route card | `<x-jp.route-card>` | Homepage routes |
| Dest card | `<x-jp.dest-card>` | Homepage destinations |
| Group card | `<x-jp.group-card>` | Homepage groups |
| Trust card | `<x-jp.trust-card>` | Homepage trust |
| Bene card | `<x-jp.bene-card>` | Feature board |
| Flight arc | `<x-jp.flight-arc>` | Route visuals |

## Search skins (Master widget + JP CSS)

| Control | Implementation |
|---------|----------------|
| Trip type tabs | Master `ota-hero-flight-search` + `search.css` |
| Airport autocomplete | `/airports/search` + `search-overrides.css` |
| Date range picker | Master widget + overrides |
| Passenger / cabin | Master widget + overrides |
| Multi-city rows | Master JS + overrides |

QA tracked in roadmap Sprint 1A.

## New stubs (ROADMAP-1)

| Component | Tag | Sprint |
|-----------|-----|--------|
| Input | `<x-jp.input>` | 1C auth migration |
| Empty state | `<x-jp.empty-state>` | 1F |
| Modal | `<x-jp.modal>` | 1D booking |
| Table | `<x-jp.table>` | 1D review |
| Payment summary | `<x-jp.payment-summary>` | 1D review |
| Booking timeline | `<x-jp.booking-timeline>` | 1D confirmation |
| Result card | `<x-jp.result-card>` | 1D results |

## Planned (not yet created)

| Component | Sprint |
|-----------|--------|
| Select / textarea wrappers | 1C |
| Loading skeleton | 1F |
| Filter bar | 1D results |
| Step indicator (checkout) | 1D |

## Usage conventions

```blade
<x-jp.button variant="primary" type="submit">Search flights</x-jp.button>

<x-jp.form-group label="Booking reference" for="reference" :error="$errors->first('reference')">
  <x-jp.input id="reference" name="reference" :value="old('reference')" required />
</x-jp.form-group>

<x-jp.empty-state
  title="No flights found"
  description="Try different dates or airports."
  action-label="New search"
  :action-url="client_route('home')"
/>
```

## CSS classes

Prefer `jp-*` in new JetPK views. Legacy views inside JP shell use `ota-*` — styled via compatibility rules in `theme.css`.

## Acceptance (per component)

- [ ] Renders in day + night theme (`data-theme`)
- [ ] Keyboard accessible
- [ ] Mobile width ≥ 320px
- [ ] Uses design tokens from `tokens.css`
