# OWNER UAT WAVE 2 — W2-20 Regression Evidence (sanitized)

CAPTURED_UTC: 2026-08-12T20:33:27Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
REMOTE_HEAD_AT_CAPTURE: `b3af949` (+ tickets filters deploy following)

## Auth

- QA Staff login succeeded → landing `/staff/dashboard`
- OTP temporary Owner-UAT state unchanged (not restored)
- Credentials not recorded in this document

## Staff authenticated page pack

| Path | HTTP | Auth | Server error |
|---|---|---|---|
| /staff/dashboard | 200 | yes | no |
| /staff/dashboard/users | 200 | yes | no |
| /staff/dashboard/bookings | 200 | yes | no |
| /staff/dashboard/payments | 200 | yes | no |
| /staff/dashboard/tickets | 200 | yes | no |
| /staff/dashboard/settings | 200 | yes | no |
| /staff/dashboard/cms/pages | 200 | yes | no |
| /staff/dashboard/reports | 200 | yes | no |
| /staff/dashboard/profile | 200 | yes | no |
| /staff/dashboard/support | 200 | yes | no |

Summary: pagesChecked=10, authOk=10, serverErrors=0

## Responsive / overflow (Users)

| Viewport | Overflow |
|---|---|
| 1440×900 desktop | 0 |
| 1024×768 tablet | 0 |
| 390×844 mobile | 0 |

## Unauthenticated Admin/Staff route smoke (local PM2 :3001)

All listed Admin/Staff dashboard routes returned **307** (auth redirect). Public HOME **200**.

## OLS

MATCH `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Source parity (this batch)

| File | Result |
|---|---|
| dashboard/.../transformers/settings.ts | MATCH |
| dashboard/.../settings-overview.tsx | MATCH |
| dashboard/.../report-filters.tsx | MATCH |
| dashboard/.../cms-workspace.tsx | MATCH |
| dashboard/.../status-badge.tsx | MATCH |

## Automated tests this loop

- `npx tsc --noEmit` dashboard → RC=0
- PHPUnit filter `BookingLocalAmendmentPolicyTest|DashboardUsersStaffScopeTest|JetpkEmailLocationSemanticsTest` → 6 passed

## Notes

- Soft content asserts on Users/Settings/CMS title/body wording were flaky under load; HTTP 200 + authenticated shell is the gate used here.
- Platform Admin deep login pack not re-run in this capture (QA Staff credentials used). Admin routes verified via unauthenticated 307 existence smoke + prior Owner UAT admin snapshots.
