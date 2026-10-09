# Booking management UAT (local Playwright)

Environment: dashboard Playwright production server (`npm run build` + `playwright.config.ts`)

| Check | Result |
|---|---|
| `BOOKINGS_VIEW_FULL_PAGE` | PASS (`booking-management-navigation.spec.ts`, `bookings.smoke.spec.ts`) |
| `PNRS_VIEW_LINKED_BOOKING_FULL_PAGE` | PASS |
| `PRIMARY_VIEW_DRAWER` | NO (no dialog on View) |
| `BOOKING_MANAGEMENT_SECTIONS` | PASS (section nav `booking-management-section-nav`) |
| `PLACEHOLDER_PAYMENT_MUTATION` | NO (buttons removed) |
| `PLACEHOLDER_REFUND_MUTATION` | NO |
| Production browser UAT | **PENDING** deploy of merged SHA |

Preview mode: fixture tests still show module-level preview notices where `NEXT_PUBLIC_DASHBOARD_MODE=preview`; live production builds must not show `PreviewDataBanner` on booking/PNR surfaces (`useDashboardLiveMode` gate).
