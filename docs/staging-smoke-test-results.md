# Staging Smoke Test Results

- Domain: `https://ota.haseebasif.com`
- Date:
- Tester:
- Build/Release reference:
- Environment: `staging`

## Preconditions

- [ ] `APP_DEBUG=false`
- [ ] `E2E_FORCE_MOCK_SUPPLIER=false`
- [ ] `php artisan ota:production-check` passed
- [ ] Duffel sandbox configured via Admin API Settings

## Public Flow Checks

| # | Check | Result (Pass/Fail) | Notes |
| --- | --- | --- | --- |
| 1 | `/` loads |  |  |
| 2 | `/flights/search` loads |  |  |
| 3 | `/airports/search?q=lhe` returns suggestions |  |  |
| 4 | `/airports/search?q=mel` returns suggestions |  |  |
| 5 | One-way search works |  |  |
| 6 | Round-trip search works |  |  |
| 7 | Multi-city search works |  |  |
| 8 | Results AJAX loads |  |  |
| 9 | Filters work |  |  |
| 10 | Flight details expand/collapse |  |  |
| 11 | `Book Now` navigates to checkout |  |  |
| 12 | Checkout page loads |  |  |
| 13 | Guest checkout path works |  |  |
| 14 | Login from checkout returns to checkout |  |  |
| 15 | Inline customer account creation works |  |  |
| 16 | International passport validation enforced |  |  |
| 17 | Review page loads |  |  |
| 18 | Confirmation page loads |  |  |

## Admin Checks

| # | Check | Result (Pass/Fail) | Notes |
| --- | --- | --- | --- |
| 19 | `/login` works |  |  |
| 20 | `/admin` dashboard loads |  |  |
| 21 | `/admin/api-settings` loads |  |  |
| 22 | Duffel readiness check works |  |  |
| 23 | `/admin/bookings` loads |  |  |
| 24 | Booking detail page usable |  |  |
| 25 | `/admin/reports` loads |  |  |
| 26 | `/admin/markups` loads |  |  |
| 27 | `/admin/branding` loads |  |  |

## Security and Safety Checks

| # | Check | Result (Pass/Fail) | Notes |
| --- | --- | --- | --- |
| 28 | Branded 500/404 shown with debug off |  |  |
| 29 | No Laravel stack trace exposed publicly |  |  |
| 30 | Duffel token not visible in UI/source/logs |  |  |
| 31 | Passport masked in customer-facing pages |  |  |
| 32 | Raw supplier payload not exposed publicly |  |  |
| 33 | Storage/document downloads are access-controlled |  |  |

## Optional Staging Playwright Smoke

```bash
STAGING_BASE_URL=https://ota.haseebasif.com npm run e2e:desktop
STAGING_BASE_URL=https://ota.haseebasif.com npm run e2e:mobile
```

## Summary

- Overall status:
- Blockers:
- Follow-up actions:
