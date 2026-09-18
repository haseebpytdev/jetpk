# CORRECTION-08 — harness + production fixes (in progress)

ARCHIVE_REJECTED_EVIDENCE_COMMIT=44823f1d2fba45f06d18b7d4911a1bc8c3b77cab
ARCHIVE_REJECTED_WORKFLOW_RUN=35341965223
ARCHIVE_REJECTED_ARTIFACT=10545163576
STATUS=PIXEL_PENDING_REVIEW
VISUAL_PASS=NO
VERIFIED_PASS=NO

## Root causes found

1. Evidence harness false positives: PASS from path + overflow only (loading/error/unstyled pages passed).
2. Admin dashboard CSS: OLS routes `/_next/*` to public Next; dashboard env had `DASHBOARD_ASSET_PREFIX=/dashboard-next` but `next.config.ts` did not apply it → CSS 404 → bare HTML.
3. Admin OV-UNKNOWN: empty Laravel API base caused SSR same-origin 404 on :3001; Laravel errors thrown as plain Error (not `ReadOnlyServiceError`).
4. Group payment live `.next` already contained CURRENT "Manual payment only" signature (CHOOSE_HOW=0); prior artifact wording likely OCR/stale UI perception — still hardening CTA/cards.
5. Homepage blank middle: capture did not progressive-scroll for IntersectionObserver/lazy reveal.

## Product changes in this release tip

- dashboard assetPrefix from `DASHBOARD_ASSET_PREFIX`
- dashboard Laravel API base SSR resolution + cookie forward + `ReadOnlyServiceError`
- group payment method card separation + full-width CTA
- auth FAB card clear
- evidence harness stable-state helpers

Do not treat this STATUS as VISUAL PASS.
