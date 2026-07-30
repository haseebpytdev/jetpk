# Passenger Forms, Validation, and PII Visual Contract (JP-UI-04)

## Scope

Traveler data collection at `/booking/passengers`. Mockup reference: **#4**.

## Page structure (shared checkout layout)

1. `BookingPageShell` with public header
2. `BookingProgress` — horizontal stepper (Seats omitted when skipped)
3. `BookingPageHeader` — “Passenger details” (or Laravel-provided title)
4. `BookingLayout`:
   - Main: `PassengerDetailsPage` form sections
   - Sidebar: `OrderSummary` (desktop sticky)
   - Mobile: `MobileOrderSummary` above main column
5. `BookingNavigationActions` — Back / Continue
6. `MobileStickyAction` — primary CTA on mobile

## Form fields

- Driven by Laravel passenger schema (adults, children, infants)
- Required fields: title, given name, family name, date of birth, nationality, passport (when international)
- Contact email and phone from booking session
- `data-testid="standard-passengers-form"`

## Validation

- Client-side: required field, date format, age vs passenger type
- Server-side: Laravel validation errors mapped to field-level messages
- No submission with incomplete required PII
- Error summary at top when multiple fields fail

## PII handling

| Rule | Implementation |
|------|----------------|
| No PII in URLs | Passenger data POST only |
| No PII in fixtures committed to repo | Audit fixtures use `audit@example.com`, synthetic names |
| Masking in logs | No `console.log` of passenger fields |
| Autocomplete | Standard `autocomplete` attributes on name/email/phone |

## Accessibility

- Every input has visible `<label>` or `aria-label`
- Error messages linked via `aria-describedby`
- Fieldset/legend for each traveller group
- Keyboard navigable; no hover-only required indicators

## Visual audit scenarios

| ID | Viewport | Theme |
|----|----------|-------|
| pax-01 | 1440 | light |
| pax-02 | 1440 | dark |
| pax-03 | 390 | light |
| pax-04 | 1280 @ 150% | light |

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| Field labels, validation messages | B | Frontend vocabulary |
| Required field schema | D | Laravel booking session |
| Traveller count | D | Search params / session |

## Deferred

- Saved traveller profiles (customer dashboard feature)
- Document upload UI (not in current Laravel contract)
