# OWNER UAT WAVE 2 — Progress Ledger

LAST_UPDATED_UTC: 2026-08-12T19:40:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LOCAL_HEAD: pending push (amendment + users/staff semantics)  
REMOTE_HEAD: `1ec26f5` on `jetpk` (pre-push)  
WAVE_1_FROZEN: `741f7d370518b5a4f32452851202653d0df9911f` (`OWNER_UAT_WAVE_1=OWNER_ACCEPTED`)

## STATUS

`OWNER_UAT_WAVE_2` = **IN_PROGRESS** (not ready for owner retest closure yet)

## SHIPPED + DEPLOYED (production dashboard rebuilds)

| Area | Notes |
|---|---|
| Money PKR display | `Rs. XX,XXX.XX`; no FX fabrication |
| Reports | Live-mode copy; no preview claim when live |
| Booking workspace | Duplicate ops panel removed; lifecycle eligibility |
| My Profile | Admin/Staff menu + `/profile` |
| Fullscreen ○ | Removed |
| Failed notifications | Classified: 74 QA SMTP 550; ops page `/notifications/failures` |
| Markup nav | `markup_settings` gate fixed |
| Deposits | List + eligibility; Approve/Reject UI blocked for Owner-UAT money safety |
| Support | Default 10/page + Prev/Next |
| Settings validation | Missing support contact → OWNER_INPUT_REQUIRED warning; supplier env demo/sandbox/live accepted |
| Users table | Compact columns + serial `01..`; security detail stays in View |
| Users filter bar | Compact filters + **More filters**; rebuild `In6djFqjlIPPlopeSNBn9`; USERS=307; OLS MATCH |

## LOCAL READY TO PUSH / DEPLOY

| Area | Notes |
|---|---|
| Booking route binding | `Booking::resolveRouteBinding` by id or booking_reference |
| W2-06 local contact | Policy + PATCH admin/staff + management UI; passenger edit blocked after PNR |
| W2-11 CMS pages | Laravel JSON store/update/archive + Next local editor (cms_pages only) |
| W2-03/04 Staff semantics | `/staff` nav + scope=staff API; Users includes Customer; Agent≠Agent Staff; Agency vs Department split |

## OLS

MATCH `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## QA_AUTH_STATE

OTP temporary Owner-UAT remains; OTP_DEMO_* preserved; QA identities active.

## REMAINING HIGH PRIORITY

- Deploy + production verify Users/Staff + amendment + CMS pages
- W2-13 compact filters on other modules
- W2-09 Settings IA
- W2-11 CMS beyond pages (sections/banners/notices/assets)
- W2-18 email Karachi semantics
- W2-21/22 typography reconcile from `ota-jetpk-w2-shell` @ `153cfaa`
- W2-20 final regression + PASS_READY_FOR_OWNER_RETEST

## NEXT_ACTION

Commit/push local batch → deploy → continue Settings / filters / email / typography.
