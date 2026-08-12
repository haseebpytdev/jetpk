# OWNER UAT WAVE 2 — Progress Ledger

LAST_UPDATED_UTC: 2026-08-12T17:45:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LOCAL_HEAD: pending commit (batch-1)  
REMOTE_HEAD: pending first push  
WAVE_1_FROZEN: `741f7d370518b5a4f32452851202653d0df9911f` (`OWNER_UAT_WAVE_1=OWNER_ACCEPTED`)

## CURRENT_TASK

W2-01 / W2-02 / W2-05 / W2-08 / W2-10 / W2-17 — first coherent repair batch.

## CURRENT_FINDING

- Money display was Intl/`{amount} {ISO}` drift; central formatter now `Rs.` for PKR and truthful ISO for others (no FX fabrication).
- Reports live UI still said “preview records”; gated behind `useDashboardLiveMode`.
- Booking Management rendered `BookingOperationalActions` twice (drawer content + sticky panel).
- Admin header fullscreen ○ control removed; My Profile route added.
- Recent booking amounts already use `DashboardMoneyPresenter::presentBookingTotal` → `displayLabel`.
- Parallel workspace branch switch briefly interrupted this wave; WIP restored from stash onto the authoritative Wave-2 branch.

## CURRENT_ROOT_CAUSE

Presentation/formatting + duplicate panel mount + missing profile surface — not a new wallet schema.

## LATEST_TESTS

- `php artisan test --filter=DashboardMoneyPresenterTest` → passed 9 / assertions 20
- `dashboard` `tsc --noEmit` → exit 0

## LATEST_PRODUCTION_PROOF

Not deployed yet this wave.

## DEPLOYED_BUILDS

—

## SOURCE_PARITY

—

## OLS_HASH

Expected (read-only): `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## QA_AUTH_STATE

OTP temporary Owner-UAT remains; OTP_DEMO_* preserved; QA Staff/Agent/Customer stay active.

## BLOCKERS

None yet.

## NEXT_ACTION

1. Commit + push Wave-2 branch to `jetpk`.
2. Run money presenter unit tests + dashboard typecheck.
3. Continue Booking action eligibility + failed notifications + deposits/markup audits.
4. Deploy dashboard/Laravel batch when tests pass.
