# Live production verification — JP-UX-PORTAL-PERF-01-R2 (+ Customer QA Draft)

**Host:** https://jetpakistan.pk  
**FINAL_ENGINEERING_SHA:** `d71e065b861657697dc5a58d0d7dc4702f71d373`  
**DEPLOYED_RUNTIME_SHA:** `d71e065b861657697dc5a58d0d7dc4702f71d373`  
**PUBLIC_BUILD_ID:** `paZNexLrycq-lEygr8Uz3`  
**LIVE_SOURCE_DRIFT:** 0  
**OLS:** PASS  

## Closed / green (not reopened)

Footer, flight-number removal, OW/pair/segmented cards, return view modal authoritative, progressive search, customer dashboard + bookings, IDOR, PIA NDC AUTH classification, return details, traveler handoff, change flight, BFCache, group detail perf.

## Customer QA local-draft live evidence (authorized)

| Gate | Result |
|------|--------|
| QA_CUSTOMER_CREATED | PASS (existing JetpkDash03 QA customer id=11) |
| QA_CUSTOMER_VERIFIED | PASS |
| QA_CUSTOMER_LOGIN | PASS |
| QA_LOCAL_DRAFT_CREATED | PASS (status=draft, detail `/customer/bookings/11`) |
| QA_DRAFT_VISIBLE_ON_DASHBOARD | PASS |
| QA_DRAFT_VISIBLE_IN_BOOKINGS | PASS |
| CUSTOMER_BOOKING_DETAIL | PASS |
| CUSTOMER_DRAFT_MANAGE_ACTIONS | PASS (Resume checkout, View draft, Contact support, Back) |
| REVIEW_TRAVELER_COMPACT | PASS (GET review only; POST review blocked) |
| CUSTOMER_BOOKING_IDOR | 0 (anon 401, agent 403) |
| SUPPLIER_MUTATION_CALLS | 0 |
| LIVE_SUPPLIER_SYNTHETIC_PASSENGER_DATA | 0 |
| PAYMENT_EXECUTED | NO |
| TICKET_ISSUED | NO |
| PNR / supplier reservation | absent |

Evidence JSON:

- `customer-qa-draft-evidence.json`
- `customer-draft-management.json`
- `customer-draft-network-safety.json`

## R2 live gates (prior + draft closeout)

| Gate | Result |
|------|--------|
| RETURN_DETAILS_COMPLETE | PASS |
| CARD_DETAILS_PARITY | PASS |
| TRAVELER_STABLE_RENDER | PASS |
| CHANGE_FLIGHT | PASS |
| BFCACHE_HISTORY | PASS |
| GROUP_DETAIL_PERFORMANCE_REGRESSION | RESOLVED |
| PERFORMANCE_CLOSEOUT | PASS |
| REVIEW_TRAVELER_COMPACT | PASS |
| CUSTOMER_BOOKING_DETAIL | PASS |
| NEARBY_DATES | NOT_FOUND_UI_ON_RESULTS |

## Safety

No Al-Haider create/cancel/token, no Sabre PNR, no payment, no ticket.  
Passengers POST creates JetPakistan-local Draft only. Review submit aborted by network guard.  
`ALHAIDER_BOOKING_ENABLED=false` preserved on prior deploy gate snapshot.

## Engineering

| SHA | Purpose |
|-----|---------|
| `d71e065b` | Draft portal detail URLs by numeric id + Resume checkout actions |
| `7c923e32` | Soft-nav passenger handoff (prior R2) |
