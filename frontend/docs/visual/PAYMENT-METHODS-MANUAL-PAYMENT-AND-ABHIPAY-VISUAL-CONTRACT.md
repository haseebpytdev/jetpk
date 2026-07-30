# Payment Methods, Manual Payment, and AbhiPay Visual Contract (JP-UI-04)

## Scope

Payment collection at `/booking/payment/manual` and `/booking/payment/card`. Mockup reference: **#10**.

## Shared layout

All payment pages use `BookingPageShell`, `BookingProgress`, `BookingLayout`, and sidebar `OrderSummary` — same contract as review and passengers.

## Payment method routing

| Method | Route | Component |
|--------|-------|-----------|
| Manual bank transfer | `/booking/payment/manual` | `ManualPaymentPage` |
| Card (AbhiPay redirect) | `/booking/payment/card` | `CardPaymentPage` |

Laravel `booking_session.available_payment_methods` determines which routes are reachable.

## Manual payment

- `data-testid="manual-payment-page"`
- Displays Laravel-provided:
  - Bank name, account title, account number, IBAN
  - Payment reference / booking reference
  - Amount due and currency
  - Upload or confirmation instructions when applicable
- Copy-to-clipboard on reference fields where implemented
- **No** invented bank details

## AbhiPay (card redirect)

- `data-testid="card-payment-page"`
- Redirect-based flow to AbhiPay — user leaves site for card entry
- **No embedded card form** in JetPakistan UI
- Visual audit gate: `forbiddenTestIds: ["embedded-card-form"]` on scenario `pay-03`
- Return URL handling preserved from existing implementation

## Security rules

| Rule | Status |
|------|--------|
| No fake card number fields | Enforced |
| No client-side card storage | Enforced |
| Payment amount from Laravel only | Enforced |
| PCI scope minimized via redirect | By design |

## Mobile

- `MobileOrderSummary` + `MobileStickyAction` for primary payment action
- Manual payment instructions scrollable; reference fields not clipped at 320px

## Visual audit scenarios

| ID | Route | Viewport | Theme |
|----|-------|----------|-------|
| pay-01 | manual | 1440 | light |
| pay-02 | manual | 1440 | dark |
| pay-03 | card (AbhiPay) | 1440 | light |
| pay-04 | manual | 390 | light |

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| Bank instructions | D | Laravel |
| Amount, currency | D | Laravel |
| Method labels | D/B | Laravel + UI vocabulary |
| AbhiPay redirect URL | D | Laravel payment service |

## Deferred

- Additional payment gateways beyond manual + AbhiPay
- In-app card tokenization
