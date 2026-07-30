# JP-UI-05 Complete Auth, Portal, and Dashboard Visual Matrix

Phase: **JP-UI-05**  
Command: `npm run audit:visual:jp-ui-05` (from `frontend/`)  
Expected count: **132** (`EXPECTED_SCENARIO_COUNT` in `jp-ui-05-scenarios.ts`)

| Application | Scenarios |
|-------------|----------:|
| frontend | 112 |
| dashboard | 20 |
| **Total** | **132** |

## Family summary

| Family | Count | Application |
|--------|------:|-------------|
| login | 20 | frontend |
| signup | 20 | frontend |
| recovery | 12 | frontend |
| manage | 20 | frontend |
| customer | 20 | frontend |
| agent | 20 | frontend |
| admin | 20 | dashboard |
| **Total** | **132** | |

---

## Login (20)

Layout matrix (12):

1. `login-light-1440`
2. `login-dark-1440`
3. `login-system-light-1440`
4. `login-system-dark-1440`
5. `login-light-1024`
6. `login-dark-1024`
7. `login-light-390`
8. `login-dark-390`
9. `login-light-320`
10. `login-dark-320`
11. `login-light-150-zoom`
12. `login-dark-150-zoom`

Interaction and state (8):

13. `login-password-visible`
14. `login-invalid-credentials`
15. `login-validation-errors`
16. `login-loading`
17. `login-session-expired`
18. `login-rate-limited`
19. `login-already-authenticated`
20. `login-social-providers-hidden-or-authoritative`

---

## Signup (20)

Layout matrix (12):

1. `signup-light-1440`
2. `signup-dark-1440`
3. `signup-system-light-1440`
4. `signup-system-dark-1440`
5. `signup-light-1024`
6. `signup-dark-1024`
7. `signup-light-390`
8. `signup-dark-390`
9. `signup-light-320`
10. `signup-dark-320`
11. `signup-light-150-zoom`
12. `signup-dark-150-zoom`

Account and validation (8):

13. `signup-customer`
14. `signup-agent`
15. `signup-validation-errors`
16. `signup-password-rules`
17. `signup-consent-error`
18. `signup-submitting`
19. `signup-success-verification-required`
20. `signup-unsupported-account-types-hidden`

---

## Recovery (12)

1. `otp-light-desktop`
2. `otp-dark-desktop`
3. `otp-light-mobile`
4. `otp-dark-mobile`
5. `otp-invalid`
6. `otp-expired`
7. `otp-rate-limited`
8. `otp-resend-state`
9. `recovery-initial`
10. `recovery-generic-success`
11. `reset-invalid-or-expired-token`
12. `reset-success`

---

## Manage booking (20)

Layout matrix (12):

1. `manage-light-1440`
2. `manage-dark-1440`
3. `manage-system-light-1440`
4. `manage-system-dark-1440`
5. `manage-light-1024`
6. `manage-dark-1024`
7. `manage-light-390`
8. `manage-dark-390`
9. `manage-light-320`
10. `manage-dark-320`
11. `manage-light-150-zoom`
12. `manage-dark-150-zoom`

Turnstile, lookup, and eligibility (8):

13. `manage-turnstile-required`
14. `manage-turnstile-failure`
15. `manage-validation-errors`
16. `manage-rate-limited`
17. `manage-not-found`
18. `manage-booking-found`
19. `manage-restricted-actions-hidden`
20. `manage-action-requires-login`

---

## Customer portal (20)

1. `customer-overview-light`
2. `customer-overview-dark`
3. `customer-overview-system-light`
4. `customer-overview-system-dark`
5. `customer-overview-mobile-light`
6. `customer-overview-mobile-dark`
7. `customer-overview-150-zoom`
8. `customer-bookings-list`
9. `customer-bookings-empty`
10. `customer-booking-detail`
11. `customer-booking-forbidden`
12. `customer-payment-detail`
13. `customer-invoice-available`
14. `customer-invoice-unavailable`
15. `customer-profile`
16. `customer-profile-validation`
17. `customer-support`
18. `customer-session-expired`
19. `customer-loading`
20. `customer-api-error`

---

## Agent portal (20)

1. `agent-overview-light`
2. `agent-overview-dark`
3. `agent-overview-system-light`
4. `agent-overview-system-dark`
5. `agent-overview-mobile-light`
6. `agent-overview-mobile-dark`
7. `agent-overview-150-zoom`
8. `agent-bookings-list`
9. `agent-booking-detail`
10. `agent-wallet`
11. `agent-wallet-unavailable`
12. `agent-ledger`
13. `agent-ledger-empty`
14. `agent-deposits`
15. `agent-deposit-pending`
16. `agent-profile`
17. `agent-staff-permitted`
18. `agent-staff-owner-route-forbidden`
19. `agent-cross-agency-not-found`
20. `agent-api-error`

---

## Admin dashboard (20)

1. `admin-overview-light`
2. `admin-overview-dark`
3. `admin-overview-system-light`
4. `admin-overview-system-dark`
5. `admin-overview-mobile-light`
6. `admin-overview-mobile-dark`
7. `admin-overview-150-zoom`
8. `admin-action-kpis`
9. `admin-bookings-list`
10. `admin-booking-detail-or-stub`
11. `admin-deposits`
12. `admin-payments`
13. `admin-agencies`
14. `admin-staff`
15. `admin-supplier-pnr-queue`
16. `admin-cancellations-refunds`
17. `admin-empty-state`
18. `platform-staff-permitted-route`
19. `platform-staff-forbidden-route`
20. `dashboard-api-or-preview-error`

---

## Registry files

- `frontend/tests/visual-audit/jp-ui-05-scenarios.ts` — scenario definitions
- `frontend/tests/visual-audit/jp-ui-05-fixtures.ts` — API/session mocks
- `frontend/tests/visual-audit/jp-ui-05-helpers.ts` — theme/viewport helpers
- `frontend/tests/visual-audit/jp-ui-05-visual-matrix.spec.ts` — frontend spec (112)
- `frontend/tests/visual-audit/jp-ui-05-dashboard-visual-matrix.spec.ts` — dashboard spec (20)
- `frontend/playwright.jp-ui-05-dashboard.config.ts` — dashboard Playwright config
- `frontend/scripts/capture-jp-ui-05.mjs` — capture orchestrator
- `frontend/scripts/verify-jp-ui-05-manifest.mjs` — manifest verifier

## Viewports

| Name | Size |
|------|------|
| `1440x900` | Desktop large |
| `1024x900` | Tablet / zoom base |
| `390x844` | Mobile |
| `320x700` | Mobile narrow |
| 150% zoom | `1024x900` @ zoom 1.5 |

## Themes

`light`, `dark`, `system-light`, `system-dark`

## JP-UI-05A unfiltered rerun

- Command: `npm run audit:visual:jp-ui-05`
- Result: **132/132 PASS** (538s)
- Hydration warnings: **0**
- React #418: **0**
- Suppression removed: `filterBenignPageErrors()` deleted from `jp-ui-05-helpers.ts`
- Report: `JP-UI-05A-FINAL-UNFILTERED-132-SCENARIO-VISUAL-REPORT.md`
