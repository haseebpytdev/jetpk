# Manage Booking — Turnstile, Lookup, and Action Eligibility Visual Contract (JP-UI-05)

## Scope

Booking lookup page (`/lookup-booking`) aligned to mockup **#9**. Turnstile behavior from JP-FE-10 remains authoritative. **No fake post-lookup actions.**

## Module

`frontend/features/standard-booking/lookup/BookingLookupPage.tsx`

## Page structure

```
PageContainer
├── Hero band
│   ├── Headline + supporting copy
│   ├── ImageSlot → /images/auth/auth-illustration.svg
│   └── BenefitStrip (trust chips)
└── Lookup card
    ├── Booking reference field
    ├── Email address field
    ├── TurnstileWidget (when required)
    ├── Error summary (data-testid="lookup-error")
    └── Submit (data-testid="lookup-submit")
```

## Hero band

- Full-width gradient/surface band above lookup card
- Headline communicates secure booking retrieval
- Illustration reuses auth SVG slot (design asset **A**; photograph deferred JP-UI-06)
- Trust chips: "Secure lookup", "Privacy protected", "Support available", "Fast access"

## Lookup form

| Field | Label | testId / ref |
|-------|-------|--------------|
| Booking reference | Booking reference | `getByLabel(/booking reference/i)` |
| Email | Email address | `getByLabel(/email address/i)` |
| Submit | Lookup CTA | `lookup-submit` |

### Validation

- Empty submit → field errors + `lookup-error` visible
- Client-side required checks before Turnstile token submission

## Turnstile (preserved)

| State | Visual | testId |
|-------|--------|--------|
| Loading config | `TurnstileUnavailableState` or loading | — |
| Required | Widget visible | `lookup-turnstile` |
| Token expired | Re-challenge prompt | existing Turnstile messages |
| Failure | Error in `lookup-error` | `lookup-error` |

Turnstile token flow unchanged from JP-FE-10. No bypass in production.

## API response states

| State | Fixture | Visual |
|-------|---------|--------|
| Default | `lookup-default` | Empty form, hero visible |
| Turnstile required | `lookup-turnstile-required` | Widget rendered |
| Turnstile failure | `lookup-turnstile-fail` | Error after submit |
| Rate limited | `lookup-rate-limit` | Error with retry guidance |
| Not found | `lookup-not-found` | Generic not-found message |
| Booking found | `lookup-found` | Booking summary (Laravel payload only) |

## Action eligibility (no fake actions)

The following elements must **not** render unless Laravel explicitly enables the capability:

| testId | Action | Status |
|--------|--------|--------|
| `lookup-change-flight` | Change flight | **Forbidden** (unsupported) |
| `lookup-add-baggage` | Add baggage | **Forbidden** (unsupported) |
| `lookup-live-status` | Live flight status | **Forbidden** (unsupported) |
| `lookup-refund-action` | Refund | **Forbidden** when login required |

Visual audit scenarios `manage-restricted-actions-hidden` and `manage-action-requires-login` enforce `forbiddenTestIds`.

## Responsive behavior

| Viewport | Behavior |
|----------|----------|
| 1440×900 | Hero + card centered; comfortable horizontal padding |
| 1024×900 | Hero stacks; card full width within container |
| 390×844 / 320×700 | Single column; hero illustration scaled; form full width |

## Accessibility

- Error summary linked via `aria-describedby` / `role="alert"`
- Turnstile iframe labeled per Cloudflare defaults
- Submit button disabled while submitting
- `:focus-visible:shadow-jp-focus` on inputs

## Test IDs

| testId | Element |
|--------|---------|
| `booking-lookup-page` | Page root |
| `booking-lookup-form` | Form container |
| `lookup-turnstile` | Turnstile widget |
| `lookup-submit` | Submit button |
| `lookup-error` | Error summary |

## Related scenarios

Manage family (20): layout matrix (12) + turnstile-required, turnstile-failure, validation-errors, rate-limited, not-found, booking-found, restricted-actions-hidden, action-requires-login.
