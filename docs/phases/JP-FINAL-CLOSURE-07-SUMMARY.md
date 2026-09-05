# JP-FINAL-CLOSURE-07 SUMMARY

## Phase name
JP-FINAL-CLOSURE-07

## Branch name
`phase/jp-email-prod-branding-02`

## Objective
Instrument and shorten the Traveler 1533ms shell→passengers GET wait. Fix booking confirmed plain-text CTA fallback and agent application plain-text facts. Do not reopen Support N30, homepage CMS, or previously proven Gmail families.

## Included scope
- Inline document-parse passengers GET plus boot timeline marks
- Exclusive NAV Timing capture and FRESH wall vs supplier classification in the Traveler runner
- Plain-text CTA reuse of the HTML action URL
- Agent application structured plain-text facts
- `--only-ids` QA send filter
- Focused Closure-07 tests

## Excluded scope
- Homepage CMS
- Support performance N30
- Resend of ticket/support/profile/group/163 inventory
- Traveler HARD_ASSIGN / fare / supplier mutation
- Customer ticket artifact generation
- SFTP, git add -A, force push

## Investigation findings
Harness T8 is `waitForURL(..., commit)` (document navigation committed). `GET /laravel/booking/passengers` previously started only after client JS hydrated `BookNowShellTimingMark` / `PassengerDetailsPage`. That interval is application-owned JS-boot wait, not supplier time.

## Root causes
- Passenger JSON request was gated on React hydration of checkout client chunks.
- Booking admin `text/plain` omitted the HTML Manage/Open action when event `cta_url` was empty.
- Agent application `text/plain` used greeting + CTA only; HTML facts were not merged.

## Exact files changed
See git status for this phase (Traveler passengers route, standard-booking API, email composer/renderer, QA command, tests, evidence runner/scripts, this summary).

## Routes changed
None.

## Database changes
None.

## Backend / frontend
Laravel email plain-text composition. Public Traveler passengers early-fetch script only. Support page, Support components, public layout, and `PublicRoutePrefetch` unchanged.

## Tests executed
- Frontend: `npx tsx --test tests/regression/passengers-early-fetch.test.ts` — 7 passed, 0 failed.
- PHPUnit OTA vendor + `APP_BASE_PATH` jetpk worktree (same Closure-06 bootstrap): 45 passed, 222 assertions, 0 failures.

## Known limitations
CUSTOMER_TICKET_ARTIFACT remains BLOCKED_NO_SAFE_LIVE_DOCUMENT.

## Risks
Early fetch uses the same `/laravel/booking/passengers` GET; failed early results fall back to the existing client GET. Duplicate POSTs are not introduced.

## Rollback
Restore Laravel files from `/home/pkjetp/releases/jp-final-07-*`. Restore previous public BUILD_ID via protected Next rollback. Do not use SFTP.

## Final status
Code is on `phase/jp-email-prod-branding-02`. Live PERFORMANCE and EMAIL gates require protected deploy + exact-build N30 + two-family Gmail send.
