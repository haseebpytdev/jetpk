# JP-UX-PORTAL-PERF-01-R3 — SUMMARY

## Phase name
JP-UX-PORTAL-PERF-01-R3

## Branch name
`phase/jp-flight-perf-01`

## Objective
Close Draft resume (server rehydrate), numeric Draft IDOR, Nearby Dates UI, and new-checkout QA customer verification before full E2E certification.

## Included scope
- Server-owned Draft resume (`CustomerDraftCheckoutResume` + `customer.bookings.resume`)
- Presenter/frontend resume path (not bare `/booking/passengers`)
- Nearby dates cache-only strip + UI prev/next
- Numeric Draft IDOR live proof
- Protected deploy of engineering SHA
- Evidence/docs correction for git/deployment reports

## Excluded scope
- Full production E2E certification
- Reopening green UX gates (footer, return view, traveler render, etc.)
- Supplier booking / PNR / payment / ticketing
- DB force-verify or auth corruption

## Investigation findings
- Resume previously pointed at bare passengers URL without rehydrating owned Draft session.
- Nearby strip timed out on live supplier fan-out → UI hidden.
- New checkout account creation requires MustVerifyEmail mail; production SMTP returns **550 User unknown** for QA `@jetpakistan.pk` addresses (including Dash03 QA customer address). Mailbox absent in virtual table (`doveadm` user missing).

## Root causes
1. No server resume that rebuilds booking draft session from owned Draft meta.
2. Nearby dates depended on live per-day supplier search.
3. No deliverable owner QA mailbox → verification email cannot send → create_account transaction fails (HTTP 500).

## Exact files changed (engineering `61362c21`)
See that commit. Evidence/docs in this phase under `docs/evidence/jp-ux-portal-perf-01/live-final-r3/` (+ corrected r2 report pointers).

## Routes changed
`GET /customer/bookings/{booking}/resume` (`customer.bookings.resume`)

## Database changes
None

## Backend / frontend changes
Resume support + nearby strip (engineering). Evidence-only for this docs commit.

## Tests executed
`CustomerPortalJsonContractTest` + `NearbyDateFareStripTest` → 11 passed (pre-deploy)

## Live gates
| Gate | Result |
|------|--------|
| Draft resume (same id #11, fresh session) | PASS |
| Numeric Draft IDOR | 0 |
| Nearby D±1 | PASS |
| New QA checkout + verify | BLOCKED_SMTP_RECIPIENT_REJECTED_NO_OWNER_QA_MAILBOX |
| LIVE_SOURCE_DRIFT | 0 |

## Known limitations / risks
Cannot complete new-customer verification path until an owner-controlled mailbox exists and accepts SMTP delivery for JetPakistan verification mail.

## Rollback
Restore prior runtime from backup `jp-ux-portal-perf-01-20260828T183110Z` via protected scripts only.

## Commit SHA
Engineering: `61362c21907b4e69ac7f399d38943dca2aa2aef4`  
Evidence: set after docs push

## Final status
`BLOCKED_SMTP_RECIPIENT_REJECTED_NO_OWNER_QA_MAILBOX`
