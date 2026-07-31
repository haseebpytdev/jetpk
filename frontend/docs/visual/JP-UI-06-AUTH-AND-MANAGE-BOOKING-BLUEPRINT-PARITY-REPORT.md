# JP-UI-06 Auth and Manage Booking Blueprint Parity Report (Wave 3)

Phase: **JP-UI-06** | Wave: **3**

## Families

| Family | Route | Mode | Shell |
|--------|-------|------|-------|
| login | `/login` | exact | `AuthPageShell` — 480px / 1fr split at xl |
| signup | `/register` | exact | `AuthPageShell` |
| manage-booking | `/lookup-booking` | exact | `BookingLookupPage` hero + floating card |

## Constraints (exception E)

- No fake social OAuth providers beyond configured Laravel contracts.
- No unsupported Change Flight / Baggage / Refund / Live Status on lookup.
- Turnstile region preserved per JP-FE-10 contract.

## Responsive / zoom

- Canonical captures: 390×844 mobile light/dark, 150% zoom desktop light.
- Overflow probes: 320×700, 375×812, 768×1024, 1024×900 (no PNG persistence).

## Evidence

`C:\Users\khadi\ota-jetpk\frontend\.visual-audit\jp-ui-06\wave-3-contact-sheet.png`

## Wave 3 gate

Complete 65-screenshot evidence + index.html. **No merge** without separate explicit approval.
