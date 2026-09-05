# JP-FINAL-CLOSURE-05 SUMMARY

## Branch
`phase/jp-email-prod-branding-02`

## Objective
Activate reviewed `88944e97`, recert Support and Traveler on the exact public build, prove Company Profile resolver with restore, send minimum post-889 email families.

## Diff gate `8bf1c2f8..88944e97`
- LARAVEL_EMAIL: operational email PHP + JetPakistan Blade partials listed in git name-status
- PUBLIC_SUPPORT: `frontend/app/(public)/support/page.tsx`, `SupportContactIsland.tsx`, `SupportTopicSearch.tsx`
- DOCS_TESTS: evidence/phases + `tests/Unit/Emails/JetpkEmailFinalClosure04Test.php`
- OTHER: none
- FLIGHT_RESULTS_HOT_PATH_CHANGED=0
- TRAVELER_HARD_ASSIGN_CHANGED=0
- FARE_AUTHORITY_CHANGED=0
- SUPPLIER_INTEGRATION_CHANGED=0

## Production activate
- PRODUCTION_RUNTIME_SHA=`88944e977c4e66d33b9cbe9515fb40308732148a`
- PUBLIC_BUILD_ID=`0NMKi-2XwkblKpudgNB3h` (prior `5GNVRs0UtH2hjOKBjoeC6`)
- DASHBOARD_BUILD_ID=`knBdbMBLDH3sxWqzoMDYu` (unchanged)
- OLS_HASH_MATCH=YES `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`
- PM2_PUBLIC=online PM2_DASHBOARD=online
- ROLLBACK_SET=`/home/pkjetp/releases/jp-final-05-889-20260905T174927Z`
- Follow-up: deployed `JetpkEmailQaRecipientLock.php` from the same SHA (method `normalizeOrFail` required by 889 `dispatchMail`; file was not in the 8bf1 subset activate). Backup `/home/pkjetp/releases/jp-final-05-lock-20260905T181243Z`

## Support N30
Evidence: `docs/evidence/jp-app-perf-closure-01/home-support-exclusive-05.json`
N_VALID=30 MIXED_BUILD=0 TOTAL_RECONCILED=YES UNATTRIBUTED_P95=0
APPLICATION_CONTROLLED_P95=1268 APPLICATION_CONTROLLED_MULTI_SECOND_ROUTE_COUNT=0 CLIENT_SOFT
Build recorded on every sample as `0NMKi-2XwkblKpudgNB3h`.

## Traveler N30
Evidence: `docs/evidence/jp-app-perf-closure-01/traveler-warm-final02r-n30.json`
N_VALID=30 MIXED_BUILD=0 TOTAL_RECONCILED=YES ACK_P95=8 VALIDATION_TO_NAV_P95=153
SHELL_TO_USABLE_APP_P95=1644 (limit 1000) — FAIL
NAV_TO_SHELL_P95=1425 (limit 750) — no same-sample DNS/TCP/TLS/origin exclusive floor
FRESH_P95=4554 (limit 2000) — supplier fare P95=6086 measured; FRESH wall not exclusively decomposed to DNS/TCP/TLS
UNSAFE_REPRICE: TRAVELER_AUTO_REPRICE_POST_COUNT=1 (one sample)
SUPPLIER_MUTATION_CALLS=0 URL authority PASS rematch>1 =0

## Email
Render greetings: admin booking/group = Dear Administrator; ticket_issued agent = Dear Agent.
Plain text audit on five rendered families: HTML/CSS/preheader/layout counts 0, human readable YES.
Profile: support_phone ORIG `+92 300 4455667` → probe `+92-51-8894405` resolver YES → restored YES. Ten render files contained probe digits.
SMTP sent 5 messages after 889 (new correlation IDs 18:14Z). Inbox verification pending ChatGPT.

## Document contract
ADMIN_BOOKING_ARTIFACT=`PROVEN_NOT_APPLICABLE_WITH_EXACT_ARCHITECTURE_REASON` — operational `booking_confirmed` admin copy is `JetpkOperationalEventMail` HTML-only.
CUSTOMER_BOOKING_ARTIFACT=`PROVEN_NOT_APPLICABLE_WITH_EXACT_ARCHITECTURE_REASON` — `BookingCommunicationService::sendBookingConfirmed` uses `BookingUniversalNotification` with no PDF; professional itinerary PDF is `BookingDocumentService::generateTicketItinerary` → `sendItineraryReady` / `itinerary_ready`.
AGENT_TICKET_ARTIFACT=`PROVEN_NOT_APPLICABLE_WITH_EXACT_ARCHITECTURE_REASON` — operational `ticket_issued` B2B/agent path is HTML notify; no PDF attach on that mailable.
CUSTOMER_TICKET_ARTIFACT=`BLOCKED_NO_SAFE_LIVE_DOCUMENT` — customer PDF attaches on `itinerary_ready` when a generated `ticket_itinerary` file exists (`PiaNdcEticketDeliveryService`); no live document inspected and no synthetic ticket created.

## Tests
OTA vendor autoload + `APP_BASE_PATH` jetpk worktree.
TEST_FILES=`tests/Unit/Emails`, `EmailBaseVariablesTest`, `CustomerFacingEmailRendererTest`, `BookingEmailCustomerCtaTest`, `CustomerFacingMailableModernLayoutTest`, `PiaNdcEticketEmailTest` (exclude admin HTTP resend).
TESTS_PASSED=32 ASSERTIONS=148 TEST_FAILURES=0
Wider HTTP admin resend tests fail under this vendor bind (`OneApiSoapTransportContract` not instantiable) — not run as production-gate suite.

## Status
PERFORMANCE_FINAL_STATUS=`BLOCKED_WITH_EXACT_PROVEN_REASON` — Support exact-build P0 closed; Traveler SHELL_TO_USABLE_APP_P95 1644 and NAV/FRESH walls lack directly measured exclusive external floors.
EMAIL_FINAL_STATUS=`PASS_PENDING_CHATGPT_GMAIL_VERIFICATION`
GMAIL_INBOX_VERIFICATION=`PENDING_CHATGPT`
