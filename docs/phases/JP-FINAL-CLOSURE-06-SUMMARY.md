# JP-FINAL-CLOSURE-06 SUMMARY

## Phase name
JP-FINAL-CLOSURE-06

## Branch name
`phase/jp-email-prod-branding-02`

## Objective
Decompose Traveler residual without changing Traveler code. Fix Gmail-discovered email defects (support CTA, same-booking itinerary, booking plain text, agent application duplication). Prove Company Profile on SMTP while a temporary probe is active, then restore. Send only the four required post-fix families plus one profile proof.

## Included scope
- Exclusive same-sample Traveler interval decomposition (`traveler-exclusive-06.json`)
- Support family CTA to existing `admin.support.tickets.show` / customer ticket routes
- Canonical QA itinerary PK-211 / JPK-2026-004821 / X7K9QP for booking_confirmed and ticket_issued
- Booking confirmed plain-text facts from payload
- Agent application: `agent-application` block only (no duplicate `detail-fields`)
- Laravel-only production activate (no public rebuild)
- Profile SMTP proof + four Gmail candidates
- Focused PHPUnit regressions

## Excluded scope
- Homepage CMS
- Resend of 163 inventory / group payment
- Support performance code
- Traveler HARD_ASSIGN / fare / supplier mutation
- SFTP, git add -A, force push

## Investigation findings
SHELL_TO_USABLE_APP_P95 1644 is sample `return-fare-final02-05`: `SHELL_TO_PASSENGERS_REQUEST` 1533 + `PASSENGERS_CLIENT_PROCESS` 111. Application-controlled residual is wait after shell before `GET /laravel/booking/passengers`.
NAV_TO_SHELL_P95 1425 is wall T8−T7 on `return-fare-final02-00`; DNS/TCP/TLS/document TTFB were not recorded, so not an external-floor PASS.
FRESH_P95 4554 sample `return-fare-final02-02`: supplier fare wait 6238 ms; VALIDATION_TO_NAV 30 ms. FRESH wall includes supplier revalidation, not only post-supplier app.

## Root causes
- Support CTA fell back to site root / lookup-booking because resolver did not use admin ticket routes and footer still had manage_url.
- booking_confirmed vs ticket_issued QA fixtures used different airline/flight while sharing booking reference/PNR.
- Plain text omitted booking facts already present in HTML payload.
- `agent_application_submitted` used default `detail-fields` plus `agent-application` structured block.
- Profile SMTP in FINAL-05 ran after restore.

## Exact files changed
Listed in git commit for this phase (email Support classes, tests, evidence scripts, this summary).

## Routes changed
None. CTA now calls existing `admin.support.tickets.show`.

## Database changes
None permanent. Temporary `agency_settings.support_phone` probe then restore.

## Backend / frontend
Laravel email only. Support and Traveler frontend unchanged.

## Tests executed
Scoped FINAL-05 email suite plus `JetpkEmailFinalClosure06Test`. See completion report.

## Known limitations
Traveler NAV/SHELL/FRESH still fail strict app gates without exclusive network capture. CUSTOMER_TICKET_ARTIFACT remains BLOCKED_NO_SAFE_LIVE_DOCUMENT.

## Risks
QA SMTP only to locked inbox. Profile mutation is reversible and restored in the same protected script.

## Rollback
Restore Laravel files from `/home/pkjetp/releases/jp-final-06-*` backup created by `activate-06.sh`. Public build unchanged.

## Final status
See terminal status in the agent completion report.
