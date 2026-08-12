# OWNER UAT WAVE 2 — Progress Ledger

LAST_UPDATED_UTC: 2026-08-12T20:00:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LOCAL_HEAD / REMOTE_HEAD: `5885dec` on `jetpk`  
WAVE_1_FROZEN: `741f7d370518b5a4f32452851202653d0df9911f` (`OWNER_UAT_WAVE_1=OWNER_ACCEPTED`)

## STATUS

`OWNER_UAT_WAVE_2` = **IN_PROGRESS**

## SHIPPED + DEPLOYED (latest)

| Area | Evidence |
|---|---|
| Users vs Staff semantics | `/staff` route; scope=staff API; STAFF=307 |
| Booking local contact amendment | Policy + PATCH; route binding by booking_reference |
| CMS pages JSON editor | Next drawer editor; CMS=307 |
| Email Karachi hardcodes removed | Seed address null; security sample city removed |
| Settings IA copy/badges | OWNER_INPUT_REQUIRED badge; read-only live copy |
| W2-21/22 typography+shell | Cherry-picked `e147367..a1e041a`; public HOME=200; dashboard BUILD `z2D--Civd9ZMK_aa3_nbj` |

## OLS

MATCH `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## QA_AUTH_STATE

OTP temporary Owner-UAT remains; OTP_DEMO_* preserved; QA identities active.

## REMAINING

- W2-11 CMS beyond pages (sections/banners/notices/assets)
- W2-13 compact filters on Bookings/Payments/Reports
- W2-09 deeper Settings overview readiness alignment
- Browser computed-font verification for Plus Jakarta / Clash
- W2-20 final regression + PASS_READY_FOR_OWNER_RETEST

## NEXT_ACTION

Browser font verify + compact Bookings filters + CMS banners/notices baseline.
