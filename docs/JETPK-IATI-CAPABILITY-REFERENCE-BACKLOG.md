# JetPakistan — IATI Capability Reference & Future Backlog

Status: **REFERENCE / TODO ONLY — DO NOT IMPLEMENT DURING OWNER RETEST V3**

Captured: 2026-08-15

## Purpose

Preserve the verified IATI.pk forensic findings as a future JetPakistan implementation reference. This document records capabilities, external integrations, internal engines, partial features, and operational polish worth evaluating later.

This document is **not authorization to port code**, deploy providers, change live configuration, or alter the current Owner Retest V3 closure scope.

## Source-of-truth reference

Authoritative forensic snapshot used for this inventory:

- Live domain tree captured from: `/home/ota.iati.pk/`
- Application/Git root captured from: `/home/ota.iati.pk/public_html/`
- Local forensic evidence root: `C:\Users\khadi\IATI-Live-Audit\Extracted\ota.iati.pk\public_html`
- Local audit reports: `C:\Users\khadi\IATI-Live-Audit-Audit\`
- Snapshot archive name: `ota-iati-pk-FULL-20260815-160916.tar.gz`
- Verified SHA256: `E49FEB3E569B991E4BDC322A7669CA26F626CD0E849F9D9C365F3C07A6CC4AE1`
- IATI Git branch at capture: `main`
- IATI Git HEAD at capture: `94d8d2c315808547b444021a2f2873a4aa0347df`
- Last committed IATI code update: `2026-07-10T21:18:21+02:00`
- Last uncommitted source change in live snapshot: `app/views/modules/flights/listing/flights.php` on 2026-07-11 local server time

Important: the forensic snapshot/codebase is the detailed reference. This JetPakistan document is only a sanitized backlog/index and intentionally contains no IATI secrets, credentials, `.env` values, or proprietary runtime data.

## Classification rules

Do not treat a module folder as proof of a complete production integration. Future JetPakistan work must distinguish:

- external supplier/API integration
- internal/manual product engine
- generic product shell
- implemented but unconfigured integration
- partial integration
- live/runtime-proven integration
- planned/comment-only work

Any future implementation must be re-designed for the JetPakistan Laravel/Next architecture rather than copied blindly from the custom PHP IATI application.

---

# 1. Flight supplier/API reference

Forensic discovery identified these real external flight-provider integrations in IATI:

1. Sabre
2. IATI API
3. Duffel
4. Amadeus
5. Amadeus Enterprise
6. Travelport
7. PKFare
8. Seeru
9. Kiwi
10. Kayak
11. Travelpayouts

### Sabre

IATI contains substantial Sabre capability and should remain an implementation reference for operational completeness checks.

Observed capability areas include:

- GDS search
- REST shopping/offers
- NDC Offer/Order flows
- pricing/revalidation
- PNR creation/retrieval related flows
- ticket issue
- void
- cancellation
- refund-related code
- ancillaries/baggage related helpers
- session/token/PCC-oriented integration code

Forensic estimate from IATI audit:

- Sabre GDS: ~85% implemented
- Sabre NDC: ~82% implemented

JetPakistan already has substantial Sabre work. Future action should therefore be a capability-by-capability completeness review, not wholesale porting.

### Important TBO correction

The IATI `modules/flights/tbo` slug was traced to `api.tequila.kiwi.com` and should **not** be treated as an independent TBO external provider without new evidence.

---

# 2. Group Ticketing reference

## Implemented external provider

### Al-Haider

Status in IATI: **IMPLEMENTED external Group Ticketing API**.

Reference areas to evaluate later in JetPakistan:

- authentication/token lifecycle
- inventory/search
- availability
- price
- hold/release behavior
- booking/passenger workflow
- provider status handling
- Admin management
- B2B visibility
- logging/error treatment

JetPakistan already contains Al-Haider work, so later work should compare operational completeness rather than recreate the integration from scratch.

## Internal/manual engine

IATI also contains a **local/manual Groups engine** (`local_groups` / LocalGroupAdapter-style architecture). This is not an external API.

Potential reusable concepts:

- manual inventory
- group publishing/visibility
- seat/allocation management
- group pricing
- B2B/B2C presentation
- group detail/search widgets
- operational Admin management

## Not implemented in captured IATI source

- Ameer-e-Millat / Ameer-e-Milat / Amir-e-Millat variants: **NOT FOUND**
- additional implemented external Group API beyond Al-Haider: **NOT FOUND**

## Planned/comment-only references

- Chaudhary
- Travel Network

These were found only as planned/comment-level provider references and must not be treated as implemented APIs.

---

# 3. Insurance reference

## UIC / United Software

Status in IATI: **PARTIAL external Insurance API (~40%)**.

Observed implementation includes insurance search/quote-side capability, but end-to-end checkout/policy issuance was not proven complete.

Reference areas for future JetPakistan implementation:

- insurance search
- plan/product selection
- destination/traveler inputs
- DOB/age handling
- premium/quote calculation
- policy booking
- policy issuance
- policy number storage
- policy PDF/certificate
- cancellation/refund handling
- Admin configuration
- B2B/B2C access
- API logging/error handling

### Current forensic verdict

- UIC external API: present and partial
- policy issuance wired end-to-end: not proven
- live insurance runtime evidence: not found in captured logs
- second external insurance provider: not found

## Local/manual insurance engine

IATI also contains a local/manual insurance adapter/engine. This is an internal product capability, not a second external provider.

Potential future JetPakistan value:

- manual insurance product setup
- pricing
- booking association
- Admin workflow
- fallback/manual issuance workflow

---

# 4. Hotels / Stays external integrations

Forensic discovery identified seven external accommodation integrations:

1. Agoda
2. Amadeus
3. Hotelston
4. Stuba
5. RateHawk
6. Travelport
7. Hotelbeds

These are real provider/module integrations, not merely the generic `hotels` product shell.

Future JetPakistan evaluation must individually verify, per provider:

- search
- hotel details
- room/rate availability
- recheck
- booking
- cancellation
- voucher
- markup/pricing
- Admin configuration
- B2B/B2C exposure
- runtime/live evidence

Do not assume every IATI hotel module is transactionally complete merely because source exists.

---

# 5. Cars / Transfers external integrations

IATI contains three real external Cars/Transfers integrations:

1. KiwiTaxi — transfer/taxi-oriented
2. Discover Cars — car rental
3. CarTrawler — car rental/mobility

A separate generic `cars` module exists as the product shell/engine and is not itself an external provider.

Future JetPakistan backlog should evaluate:

- search/availability
- pickup/drop-off
- transfer vs rental distinction
- pricing/recheck
- booking
- voucher
- cancellation/refund
- traveler/driver details
- Admin configuration
- B2B/B2C workflows
- markup/commission
- supplier logging

Current evidence proves external integration source exists; it does not yet prove every provider has a complete end-to-end production lifecycle.

---

# 6. Tours / Activities external integrations

IATI contains three external Tours/Activities integrations:

1. Viator
2. Viator Merchant
3. Tiqets

A generic `tours` module also exists and should be treated as the product engine/shell.

Future JetPakistan evaluation areas:

- destination/activity search
- product/details
- availability/time slots
- pricing
- booking
- voucher/ticket
- cancellation/refund
- Admin management
- B2B/B2C
- markup/commission
- supplier/webhook handling

As with Cars/Hotels, source presence is proven; complete production maturity must be re-verified per provider before implementation decisions.

---

# 7. Promotions / Promo Codes

IATI forensic audit classified Promo Codes at approximately **85% implemented**.

Observed capability areas:

- Admin CRUD
- checkout/application logic
- flight applicability
- stay applicability
- tours applicability
- Umrah applicability
- validity/discount logic
- redemption-oriented workflow

Future JetPakistan backlog:

- finish/verify first-class Admin Dashboard management
- ensure quote/review/checkout validation is authoritative server-side
- product scoping
- supplier/airline/route restrictions where needed
- B2B/B2C targeting
- agency/customer restrictions
- percentage/fixed discount
- usage limits
- redemption audit
- invoice/refund interaction

JetPakistan already has PromoCode backend/service/test foundations. Treat IATI primarily as an operational/UI/workflow reference.

---

# 8. Payments reference

IATI discovery identified multiple payment/gateway integrations (audit reported seven external payment integrations), plus internal payment/finance functionality.

Before any JetPakistan implementation, the exact provider registry must be re-read from the detailed IATI audit report and each provider classified as:

- actual live gateway
- configured-but-unused
- skeleton/plugin
- webhook-capable
- refund-capable

Future JetPakistan implementation should prioritize architecture/security/compliance over direct code reuse.

---

# 9. Finance / Accounting operational capabilities

IATI contains operational concepts worth preserving as future JetPakistan polish references:

- wallet
- agency credit
- deposits
- ledger/transactions
- commissions
- markups
- service fees
- invoice handling
- refund-related finance handling
- reporting/logging

Future JetPakistan work should integrate these into one coherent Admin/Agent operational system with immutable completed history and controlled mutations.

---

# 10. B2B / Agent operational reference

IATI includes broad B2B/Agent capabilities such as:

- agent registration
- agency relationships
- agent staff
- approval/status
- credit/wallet/deposits
- supplier visibility
- booking
- ticketing/invoice-oriented workflows
- profile management
- B2B pricing/markup concepts

Future JetPakistan work should use these as workflow references while preserving JetPakistan's own RBAC, service boundaries, and security model.

---

# 11. Admin operational reference

IATI contains broad Admin functionality across:

- bookings
- users/customers
- agents/agent staff/agencies
- finance/credits/deposits/transactions
- supplier/modules configuration
- airlines
- reports
- booking logs
- search logs
- promo codes
- groups
- insurance
- CMS/content/settings
- OAuth / 2FA / security
- release notes/updates

Future JetPakistan operational polish should ensure equivalent required workflows are first-class in the current Next Dashboard rather than hidden in legacy routes.

---

# 12. CMS / SEO / Content reference

IATI contains:

- homepage/content management concepts
- blog/content
- menus
- branding
- SEO
- sitemap
- robots
- uploads/banners/assets

Future JetPakistan work should align this with the approved structured CMS/Page Builder roadmap rather than reproduce legacy IATI patterns.

---

# 13. Authentication / Security reference

IATI contains or references:

- session authentication
- password flows
- Google OAuth
- 2FA
- roles/role checks
- account controls
- API/webhook authentication concepts

Security warning from forensic audit: legacy/public artifacts such as `install/`, `diagnostic.php`, `migrate.php`, `dump_schedule.php`, and debug artifacts should be treated as **DO NOT PORT** patterns.

---

# 14. Background operations / logs / webhooks

IATI contains background/operational concepts including:

- supplier status refresh
- cron/API cron routes
- supplier/search logging
- booking/search reports
- webhooks
- runtime error/audit logs

Future JetPakistan operational polish should preserve equivalent observability using Laravel jobs/queues/scheduler, structured audit logs, and secure webhook verification.

---

# 15. Known IATI development chronology

Forensic evidence at capture:

- last supplier/API commit identified: Sabre ancillary work on 2026-07-07
- Git HEAD on 2026-07-10: Google OAuth redirect URI fix
- uncommitted `flights.php` parallel-search/UX change after HEAD
- Sabre/IATI runtime search logging continued through 2026-07-31
- HTTP/runtime traffic observed through 2026-08-15

Current remote developer work newer than the forensic snapshot was **not verified** because remote cloning/fetching was unavailable during the audit. Do not infer that July 10 is necessarily the latest private/un-deployed developer work.

---

# 16. Future JetPakistan TODO principles

When this backlog becomes active:

1. Re-audit JetPakistan's then-current capabilities first.
2. Re-open the IATI forensic codebase/reports as the source reference.
3. Never copy credentials/configuration.
4. Never blindly port custom PHP code into Laravel/Next.
5. Prefer `REIMPLEMENT_CONCEPT` or `PORT_LOGIC_AFTER_REVIEW` over copy/paste.
6. Preserve JetPakistan's RBAC, API Connections architecture, auditability, tests, and deployment gates.
7. Every external provider must have explicit feature gates and safe credential management.
8. Every mutation must be server-authoritative and idempotent where appropriate.
9. Every financial operation must preserve immutable history/audit metadata.
10. Every provider integration requires focused tests, full regression, production-safe deployment, and rollback evidence.
11. Every new module should receive full Admin operational management where appropriate, not backend-only implementation.
12. Complete UX/operational polish should be included with implementation: dashboards, statuses, errors, notifications, reports, logs, RBAC, and monitoring.

---

# 17. Suggested future implementation buckets

These are backlog categories only; sequence must be re-prioritized when the phase becomes active.

## A. Insurance

- UIC external Insurance API end-to-end
- policy issuance/certificate
- Admin management
- B2B/B2C integration
- manual/local insurance fallback engine

## B. Ground products

- Cars/Transfers product architecture
- KiwiTaxi
- Discover Cars
- CarTrawler

## C. Tours/Activities

- Viator
- Viator Merchant model where still commercially relevant
- Tiqets

## D. Hotels/Stays expansion

Evaluate:

- Agoda
- Amadeus Hotels
- Hotelston
- Stuba
- RateHawk
- Travelport Hotels
- Hotelbeds

Do not integrate all automatically; commercial agreements, API access, technical quality, and business value must determine final selection.

## E. Flight supplier breadth

Evaluate only genuine missing/commercially useful suppliers from the IATI registry. Do not duplicate providers already better implemented in JetPakistan.

## F. Promotions

- complete Admin Promo Code operational management
- targeting/restrictions
- redemption history
- reports/audit

## G. Operational polish

- supplier/API operational dashboards
- Admin action completeness
- finance/wallet/credit/deposit lifecycle
- commission/markup management
- reports
- cron/job observability
- webhook observability
- notification/email operational management
- hidden/backend-only feature surfacing where safe

---

# 18. Out-of-scope / do-not-port reference

Do not port merely because it exists in IATI:

- legacy installer pages
- diagnostics exposed through webroot
- ad-hoc migration scripts
- debug artifacts
- unstructured secret/config handling
- fragile custom-PHP routing patterns
- dead/orphaned modules
- generic supplier wrappers that masquerade as separate providers
- modules without verified commercial/API relevance

---

# 19. Source-reference status

`IATI_FORENSIC_REFERENCE_CAPTURED=YES`

`IATI_REFERENCE_HASH_VERIFIED=YES`

`IATI_SOURCE_TREE_COMMITTED_TO_JETPK=NO`

`IATI_SECRETS_COMMITTED_TO_JETPK=NO`

`IATI_REFERENCE_IS_TODO_ONLY=YES`

`OWNER_RETEST_V3_SCOPE_CHANGED=NO`

`CURRENT_IMPLEMENTATION_AUTHORIZED=NO`

This backlog remains dormant until the active JetPakistan production-closure/Owner Retest phases are explicitly completed and a new implementation phase is authorized.
