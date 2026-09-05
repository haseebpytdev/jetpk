# JP-FINAL-CLOSURE-04 SUMMARY

## Branch
`phase/jp-email-prod-branding-02`

## Objective
Close remaining P0 home→support app-controlled residual and remaining P1 email defects. Do not reopen closed performance architecture.

## Included
- Exclusive-interval home→support N=30
- Role-aware greeting from intended recipient role
- Ticket itinerary/PNR semantics
- Structured plain text
- Contextual CTAs
- Agent application fields
- Canonical responsive audit pointer
- Sanitized Gmail reconciliation manifest
- Support RSC heading split (not yet on production public build)

## Excluded
Homepage CMS, trending routes, Featured Deals, Country Tours, P2 cleanup, 163-email resend, fare-safety weakening.

## Production lineage (protected wrapper, LF stdin)
LOCAL_HEAD=`8bf1c2f8600970ab9ba946f42928be80743b1e20`
PRODUCTION_RUNTIME_SHA=`8bf1c2f8600970ab9ba946f42928be80743b1e20`
PUBLIC_BUILD_ID=`5GNVRs0UtH2hjOKBjoeC6`
DASHBOARD_BUILD_ID=`knBdbMBLDH3sxWqzoMDYu`
OLS_HASH_MATCH=`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`
DISK_USED_PCT=21
INODE_USED_PCT=3
LOAD=`0.05 0.16 0.10`
PM2_PUBLIC=online
PM2_DASHBOARD=online
FINAL_OPERATION_RC=0
CRLF_SHELL_FAILURE_COUNT=0 (Python LF pipe; PowerShell Get-Content pipe is forbidden)

## Performance evidence
`docs/evidence/jp-app-perf-closure-01/home-support-exclusive-04.json`
N=30 CLIENT_SOFT MIXED_BUILD=0 TOTAL_RECONCILED=YES
APPLICATION_CONTROLLED_P95=1620
APPLICATION_CONTROLLED_MULTI_SECOND_ROUTE_COUNT=0
ORIGIN_SERVER_P95=183
EXTERNAL_NETWORK_P95=669
UNATTRIBUTED_P95=0

Previous 2088ms “app” figure used overlapping `usable − last_rsc.duration` subtraction. Same-sample exclusive intervals on current runtime do not produce a >2s application-controlled route.

NAV_TO_SHELL / FRESH traveler N=30 was not re-run in this loop (do not reopen Traveler HARD_ASSIGN). Last ChatGPT-cited walls remain NAV_TO_SHELL_P95=1434 and FRESH_P95=9242.

## Email architecture proof
BOOKING_ITINERARY_ARTIFACT=`PROVEN_NOT_APPLICABLE`
Ticket/PDF itinerary is a separate `BookingCommunicationService` / `BookingDocumentService::generateTicketItinerary` / `itinerary_ready` path. `booking_confirmed` operational mail is HTML itinerary only.

TICKET_ARTIFACT=`PROVEN_NOT_APPLICABLE` for the operational `ticket_issued` family (HTML itinerary + ticket numbers). PDF attaches only when a generated `ticket_itinerary` document exists (`PiaNdcEticketDeliveryService`). QA sample send must not create synthetic tickets.

PROFILE_RESOLVER_PROVEN=`NO` (resolver is `CompanyEmailProfileResolver` in QA/render path; live profile mutation/restore not executed this loop).

## Tests
`vendor/bin/phpunit tests/Unit/Emails/JetpkEmailProdQaHelpersTest.php tests/Unit/Emails/JetpkEmailFinalClosure04Test.php`
TESTS_PASSED=14 ASSERTIONS=63 TEST_FAILURES=0

## Status
PERFORMANCE_FINAL_STATUS=`BLOCKED_WITH_EXACT_PROVEN_REASON` — home→support multi-second app route closed; traveler NAV/FRESH walls not re-certified this loop.
EMAIL_FINAL_STATUS=`BLOCKED_WITH_EXACT_PROVEN_REASON` until targeted Gmail resend of changed families + profile restore proof + Laravel deploy of greeting/CTA/plaintext/ticket HTML.
GMAIL_INBOX_VERIFICATION=`PENDING_CHATGPT`
