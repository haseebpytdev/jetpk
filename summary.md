# Agent summary map (`summary.md`)

**Purpose:** Single skim file so agents open fewer PHP sources. **Not** a substitute
for `SPEC.md` / `AGENTS.md` rules — read those first.

**Maintenance (required):** Same changeset as the code — not a follow-up task.
When you add/remove/rename **public** APIs, move responsibility between files, or
change behavior of anything indexed below, update the relevant section here and
append a **Changelog** row for notable module-level edits. If your change is
outside current tables but is a new high-traffic path, add a short entry so the
next agent does not miss it. Rules: `AGENTS.md` → *Summary documentation*,
`SPEC.md` non-negotiable #13 and *Definition of Done*.

**Last updated:** 2026-09-03 (JP-HOME-UI-01 homepage shell polish)

## Concurrent Codex UI Work — JP-HOME-UI-01

**Status:** Integrated into `phase/jp-flight-perf-01` (JP-APP-PERF-CLOSURE-01).
**Branch / worktree:** `phase/jp-home-ui-01-codex` / `C:\Users\khadi\ota-jetpk-codex-home-ui`
**Source HEAD:** `b6c1943dc2164faceb3d42f76dc33c05bd769b92`
**Current Codex HEAD:** branch tip containing this entry (exact SHA in the final return)

**Scope / behavior:** Homepage hero now contains the full search/trust composition with no pale gap or divider; the existing Login action uses compact branded styling; full header/FAB collapse aligns at `lg`; the existing FAB has polished open/close, focus, and touch behavior. No search logic changed.
**Files changed:** `PublicHero`, `SiteHeader`, `DesktopNavigation`, `PublicFloatingActionDock`, `globals.css`, targeted Playwright coverage, and this entry.
**Tests:** production build and lint pass; JP-HOME-UI-01 Playwright 7/7 at 1440/1280/1024/768/390; existing search payload contract 7/7.
**Integration status / Cursor action:** Cherry-picked e33f3ea320b60cd35d7c510c1fd2c7992e59f539 onto performance HEAD 482632e6 with semantic review. CODEX_DIFF_REVIEW=PASS; NO_PERFORMANCE_LOGIC_OVERWRITE=YES; NO_SEARCH_BUSINESS_LOGIC_CHANGE=YES; NO_DUPLICATE_COMPONENT=YES; NO_NEW_RUNTIME_DEPENDENCY=YES.

| Date | Phase | Notes |
| --- | --- | --- |
| 2026-09-02 | JP-UX-POLISH-02B | Fail-closed airline logo identity: prefer local travel-assets masters; block generic IATA CDN for collision codes (PF AirSial, 9P Fly Jinnah). **`AirlineBrandingService::publicUrlForLocalMaster()`**, **`AirlineLogoCacheService::isIataOnlyDownloadBlocked()`**. Evidence `docs/evidence/jp-ux-polish-02b/`. |
| 2026-08-31 | Google OAuth Admin API | **`SupplierProvider::GoogleOauth`** managed like SMTP via Admin API settings. **`GoogleOauthConfigResolver`**: active complete DB → `services.google.*`, else ENV fallback. Blank secret preserves stored. Test Configuration = completeness + Socialite driver resolve only (no token exchange). |
| 2026-08-30 | JP-FINAL-CLOSURE-01-R5 | Traveler GET: preserve draft `search_id`; skip duplicate Sabre validate when recent revalidation exists; release session before offer/supplier I/O; `PassengersRequestTiming` S0–S8; soft-nav shell via client passengers page + loading T8; session bootstrap 2.5s timeout. |
| 2026-08-28 | JP-FLIGHT-PERF-01-R1 | Default results sort **Cheapest**; Pair-first return `view`; pending UX “Updating fares…”; bounded fare-change accepts; passengers silent auto-revalidate; passengers shell-first loading + OCR on Autofill only. Evidence `docs/evidence/jp-flight-perf-01/`. Deploy blocked on Al-Haider `60175` manual cancel. |
| 2026-08-28 | JP-GRP-COMM-01-R2 | **`GroupReservationService`**: supplier cancel must succeed before local `held_seats` decrement; `SupplierReleaseFailed` keeps seats held. **`ALHAIDER_CANCEL_ENABLED`** separated from **`ALHAIDER_BOOKING_ENABLED`**. **`AlHaiderGroupBookingPayloadBuilder`**: fail-closed (no synthetic passenger/contact data); seat_count = adults+children. Admin manual reconcile + retry-supplier-release. Tests: `GroupReservationSupplierReleaseAtomicityTest`. |
| 2026-08-26 | JP-API-CMS-FINAL-CLOSEOUT | **Al-Haider `managed_token`:** DB `SupplierConnection` is sole token authority; `AlHaiderManagedTokenRenewalService` renews only on genuine expiry with lock + persistent 1/365d issuance budget + ambiguous fail-closed; `AlHaiderClient::isConfigured()` recognizes DB tokens without ENV secrets; Test Connection uses read-only groups probe (zero login); official reserve/cancel paths corrected. **SMTP:** active-invalid DB → ENV fallback. **Nav:** single **API & Modules** sidebar entry. **CMS:** connected-field production-truth matrix tests. |
| 2026-08-26 | JP-BO-04G-SANDBOX-CANCEL | **Sandbox-safe admin Cancel PNR:** `SupplierPublicRoutingGuard` excludes non-live from public/agent fanout; `SabreSandboxQaLifecycleGuard` + `sabre:ensure-sandbox-qa-connection`; admin direct cancel modal + eligibility/`cancel_pnr_context`; request reuse. Network sandbox lifecycle **blocked** until CERT env credentials exist. Tests: `SupplierPublicRoutingAndSandboxQaGuardTest`, `AdminDirectCancelBookingTest`. |

---

## Changelog (high level)

| 2026-09-03 | JP-APP-PERF-CLOSURE-01 traveler | Book Now handoff no longer window.stop()/strips prefetch; prefetch /booking/passengers during revalidation. |
| 2026-09-03 | JP-APP-PERF-CLOSURE-01 | Return Pair poll short-circuit skips consolidator/one-way mapper on empty/partial polls; early partial onProgress throttled to 1st/every-3rd until first persisted pair; SearchModule prefetches resultsPath before hard-nav. |
| 2026-09-03 | JP-HOME-UI-01 | Next homepage hero keeps search + trust tags inside the image-backed flow, removes the pale spacer/divider, modernizes Login, and aligns full-header/FAB visibility at `lg`; existing FAB receives tokenized visual/focus/motion polish. No search logic or runtime dependencies changed. |
| (older) | see archive | Full historical changelog: `docs/archive/summary-changelog-history-pre-2026-09-03.md` |

---

## Quick orientation

| Read first | Why |
|------------|-----|
| `SPEC.md`  | Stack, folders, non-negotiables, response format. |
| `AGENTS.md`| Workflow, coding rules, file-summary convention. |
| `summary.md` | **This file** — where logic lives, key public methods. |
| `docs/iati-parity-roadmap.md` | **E0** IATI parity master checklist (E1–E12 phases, feature table, permissions, do-not-copy). |
| `.env.example` | Canonical env keys (no secrets in repo). |

**Entry routes:** `routes/web.php` (most UI). `bootstrap/app.php` — middleware
bootstrap. **Supplier wiring:** `SupplierAdapterResolver`, `SupplierBookingService`,
`BookingProviderRouter`.

---

## Config surface (where behavior is toggled)

| File | Role |
|------|------|
| `config/ota-mobile.php` | Mobile app shell preference + **`mobile_pages`** route map. |
| `config/ota-ui.php` | UI version channels (**site** / **admin** / **staff**): defaults, active versions, preview flags; critical view audit list. |
| `config/ota.php` | General OTA toggles. |
| `config/ota_client.php` | Multi-client deployment profile (`OTA_CLIENT_SLUG`, `OTA_ACTIVE_THEME`, `OTA_PUBLIC_ASSET_PROFILE`, `OTA_MODULE_*`); separate from `ota-client` branding. |
| `config/ota-client.php` | Branding/contact fallbacks (agency name, colors, support email). |
| `config/ota-flights.php` | Flight search / offer behavior. |
| `config/ota-suppliers.php` | Supplier-level flags. |
| `config/suppliers.php` | Sabre/Duffel connection + booking/ticketing flags (B13: `revalidate_path` default `/v4/shop/flights/revalidate` / `revalidate_before_booking` (env default true in B21); **B21:** `allow_createbooking_without_revalidation` / `SABRE_ALLOW_CREATEBOOKING_WITHOUT_REVALIDATION`; **B22/B23/B29/B30/B31/B32/B33/B34/B35/B36/B37:** `createbooking_payload_style` / `SABRE_CREATEBOOKING_PAYLOAD_STYLE` (nested + **B23** root `*_root_v1` + `trip_orders_product_array_v1` + **B29** `trip_orders_flight_offer_camel_v1` / `trip_orders_flight_details_camel_v1` + **B30** `trip_orders_flight_details_full_camel_v1` + **B31/B32** `trip_orders_flight_details_sabre_v1` / **B33** `trip_orders_flight_details_sabre_agency_v1` + **B34** five `*_sabre_agency*` / `*_rootAgencyPhone_v1` / `*_phoneNumbers_v1` + **B35** PNR phone row styles + **B36** `*_pos_source_phone_v1` / `*_pos_phone_v1` / `*_agency_root_camel_v1` / `*_travelAgency_v1` / `*_customerInfo_phone_v1` compare experiment styles + **B37** PNR `phoneLine` / `phoneLines` / `contactNumbers` / `pnrContact` / `reservationContact` / `contactInfo.agencyPhone` / `travelers[].phone` compare styles — B32: wire `contactInfo`; **B33/B34/B35/B36/B37:** `agency_phone`, `agency_phone_country_code`, `agency_phone_type`, **B36** `agency_name`, `agency_city`, `agency_country`, `agency_pos_phone_use_type`, **B37** `agency_phone_location` / `SABRE_AGENCY_PHONE_LOCATION`); B16–B17: `revalidate_payload_style` / `SABRE_REVALIDATE_PAYLOAD_STYLE`, incl. `client_gds_revalidate_v1`; B19: `.env.example` recommends `/v4/offers/shop/revalidate` when tenant hits 27131 on BFM path). |
| `config/supplier_credentials.php` | Credential storage shape (no secrets). |
| `config/services.php` | Mail, Socialite, etc. |

---

## `app/Services/Finance/Ledger/` — double-entry ledger (Phase 1)

| File | Responsibility | Public API (representative) |
|------|----------------|---------------------------|
| `LedgerAccountService.php` | Chart of accounts seed/lookup. | `seedSystemAccounts`, `findByCode`, `listActive` |
| `LedgerTransactionFactory.php` | Draft tx + ref sequencing + actor/guest assignment. | `createDraftTransaction`, `generateRef`, `assignSource`, `assignActor`, `assignGuest`, `sourceAlreadyPosted` |
| `LedgerPostingService.php` | Double-entry lines, balance check, rule posting. | `addDebit`, `addCredit`, `validateBalanced`, `post`, `postFromRule` |
| `LedgerBalanceService.php` | Posted entry balances; agency wallet compare sums all `agent_wallets` per agency. | `getAccountBalance`, `getAgencyWalletBalance`, `getAgencyAgentWalletBalance`, `getPlatformExposure`, `getAgencyBalance`, `compareWalletToLedger` |
| `LedgerReversalService.php` | Reverse posted transactions. | `reverse` |
| `LedgerReconciliationService.php` | Project/backfill from existing finance records; wallet reconcile. | `projectExistingEvents`, `backfillExistingEvents`, `reconcileWalletTransactions`, `compareWalletBalanceToLedger`, `comparePaymentsToLedger`, `findDuplicateSourcePosts`, `findOrphanWalletTransactions` |
| `LedgerStatementService.php` | Period statements. | `buildMonthlyStatement`, `buildPlatformStatement` |
| `LedgerIntegrityService.php` | Integrity + balance verification for commands. | `checkIntegrity`, `verifyBalances` |
| `LedgerQueryService.php` | Read-only UI queries (list, detail, filters, CSV export rows); agency eager-load uses **`Agency::restrictedSelectColumns()`** (schema-safe, no `code` column required). | `buildIndexPayload`, `paginate`, `findForShow`, `entryTotals`, `exportRows`, `csvRows` |
| `LedgerReconciliationDashboardService.php` | Reconciliation dashboard metrics for accounting UI; agency label code from **`AgencyPrefixService`**. | `buildPlatformDashboard`, `buildAgencySummary`, `csvRows` |
| `LedgerEventRecorder.php` | Go-forward parallel posting for live finance events; manual adjustments throw on failure (atomic with wallet). | `recordAgencyDepositApproved`, …, `recordManualWalletCredit`, `recordManualWalletDebit` |
| `Adjustments/ManualWalletAdjustmentService.php` | Platform-admin manual wallet credit/debit/reversal with idempotency + audit log. | `apply`, `reverse`, `findByIdempotencyKey`, `canReverse`, `walletsForAgency`, `requiresWalletSelection` |
| `Dashboard/AdminFinanceDashboardService.php` | Read-only platform finance ops dashboard (wallet vs ledger, MTD, recent activity). | `build`, `csvRows` |
| `Export/ManualWalletAdjustmentExportService.php` | Read-only CSV rows for manual adjustment audit export. | `csvRows` |

Artisan: `ledger:seed-accounts`, `ledger:project-existing`, `ledger:backfill`, `ledger:reconcile`, `ledger:check-integrity`, `ledger:verify-balances`, `ledger:posting-status`. **Live hooks (Phase 4):** deposit approve, payment verify, refund approve/paid, commission approve, markup on Confirmed/Ticketed — failures log only; historical backfill still deferred.

---

## `app/Services/Finance/Statements/` — agent wallet statements (read-only)

| File | Responsibility | Public API (representative) |
|------|----------------|---------------------------|
| `AgentStatementService.php` | Agency wallet statement from `agent_wallet_transactions` (source of truth) + ledger liability compare. | `resolvePeriodFromRequest`, `buildAgencyIndexRows`, `buildStatement`, `openingBalanceBefore`, `csvRows` |

Routes: `admin.finance.statements.*`, `staff.finance.statements.*` (`staff.reports.view` / `staff.reports.export`), `agent.finance.statement.*` (`agent.reports.view` or `agent.ledger.view`). Policy: **`FinanceStatementPolicy`**.

---

## `app/Services/Booking/` — booking domain

| File | Responsibility | Public API (representative) |
|------|----------------|---------------------------|
| `BookingService.php` | Draft booking, passengers/contact/fare, submit, status transitions, staff assignment, notes, status logs. | `createDraftBooking`, `attachPassenger(s)`, `attachContact`, `attachFareBreakdown`, `submitBookingRequest`, `getAllowedStatusTransitions`, `changeStatus`, `addInternalNote`, `assignStaff`, `addStatusLog` |
| `BookingProviderRouter.php` | Routes supplier booking + checkout gating messages. | `checkoutBlockedMessage`, `createSupplierBooking` |
| `BookingActionStateService.php` | Aggregated action/state payload for a booking (UI / API). | `build` |
| `BookingOperationalPrecheckService.php` | Passenger readiness validation before checkout-style steps. | `validatePassengerReadiness` |
| `InternationalRouteDetector.php` | International vs domestic, PK domestic, passport vs national ID rules from offer/airports. | `isInternational`, `isPakistanDomesticForTravelDocuments`, `nationalIdTravelDocumentsAllowedForOffer`, `requiresPassportOnlyTravelDocuments`, `distinctCountryBucketsFromOffer` |

---

## `app/Services/Bookings/` — fare hold

| File | Responsibility | Public API |
|------|----------------|------------|
| `FareHoldService.php` | Checkout hold prep, offer validation hook, supplier hold orchestration, session refresh/expiry, revalidation before confirm, hold completed/failed. **`supplier_offer_id`** via **`BookingHoldSessionSupplierOfferIdResolver`** (short offer id; full raw ref in snapshot). | `prepareCheckoutHold`, `validateOfferForCheckout`, `canSupplierHoldOffer`, `createHoldIfSupported`, `refreshHoldSession`, `isHoldExpired`, `requiresFinalRevalidation`, `revalidateBeforeConfirmation`, `markHoldCompleted`, `markHoldFailed` |

---

## `app/Services/FlightSearch/`

| File | Responsibility | Public API |
|------|----------------|------------|
| `FlightSearchService.php` | Agency-scoped search + metadata wrapper; nearby departure origin expansion + direct-only post-filter; Sabre rows get `fare_verification_digest` / `expected_ui_price`. JP-LARAVEL-PERF-01: eligibility skip map once + `SearchPerfTrace` T6–T13 / provider start offsets. | `search`, `searchWithMeta` |

**Adapters (supplier search):** `app/Services/Suppliers/Adapters/SabreFlightSupplierAdapter.php`,
`DuffelFlightSupplierAdapter.php` — implement search against provider APIs;
resolved via `SupplierAdapterResolver`.

---

## `app/Services/Suppliers/` — shared supplier layer

| File | Responsibility | Public API |
|------|----------------|------------|
| `SupplierAdapterResolver.php` | `SupplierProvider` enum → `FlightSupplierInterface`. | `resolve` |
| `SupplierBookingService.php` | Eligibility, manual PNR, automated create with preflight guard. IATI delegates to **`IatiSupplierBookingEligibility`**. | `isBookingEligible`, `markManualPnr`, `createSupplierBooking` |
| `SupplierBookingPreflightGuard.php` | Duplicate PNR/attempt gates before supplier HTTP (9D-3); E1B controlled defer bypass; F9D **`SabreControlledPnrApprovalOverrideGate`** for approved controlled-command defer bypass; F9F **`SabreControlledPnrRetryAllowanceGate`** for one-shot fare-acceptance retry on `controlled_pnr_command`; F9J **`SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate`** for one-shot post-F9I clean-digest retry after F9F consumed; F9L **`SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate`** for one-shot post-F9K recovery after F9J pre-HTTP schema failure; F9Q **`SabreControlledFinalPnrRetryAllowanceGate`** for one-shot final retry after F9P green when F9F+F9J+F9L consumed; BF7-J-OPS-FIX1 operational defer bypass; BF7-J-OPS-FIX3 **`SabreOperationalAllowNnStrategyChangedRetryGate`** for one-shot NN strategy-changed admin/staff retry. | `preflightAutomatedCreate`, `assertManualPnrAllowed`, `recordManualPnrAttempt` |
| `SabreControlledPnrRetryAllowanceGate.php` | F9F one-shot controlled retry past `supplier_booking_retry_not_allowed` after F9C + F9E on exact controlled create confirm only. | `allows`, `recordUsage`, `retryAllowanceAlreadyUsed` |
| `SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate.php` | F9J one-shot controlled retry after F9F used + prior NO FARES/RBD/CARRIER + clean post-F9I payload digest on exact controlled create confirm only. F9K: schema-only failure recovery (`schema_validation_failed` without host ApplicationResults). | `allows`, `assessAvailability`, `recordUsage`, `recordSchemaValidationOutcome`, `markHostApplicationResultsReceived`, `retryAllowanceFullyConsumed`, `retryAllowanceAvailableForRecovery` |
| `SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate.php` | F9L one-shot post-F9K recovery when F9J consumed by pre-HTTP schema validation failure + current CPNR schema/digest pass on `controlled_pnr_command` only. | `allows`, `assessSchemaRecoveryAvailability`, `buildF9jAccountingDiagnostics`, `recordUsage`, `schemaFixRecoveryAlreadyUsed` |
| `SabreCpnrIatiWireSchemaValidator.php` | F9K local pre-HTTP CPNR v2.4 AirPrice schema validation (safe pointer/message only). | `validateIatiLikeCpnrV24AirPrice`, `validateCpnrEnvelope`, `outcomeLooksLikeCpnrSchemaValidationFailure` |
| `SabrePassengerRecordsPayloadDigest.php` | F9H/F9I/F9J/F9K safe CPNR wire digest (AirBook/AirPrice + context comparison); hard/warning risk + brand diagnostics; F9J clean-digest helpers require schema pass; VC from FlightQualifiers. | `digest`, `commandSummaryFromDigest`, `isPostF9iCleanForControlledRetry`, `postF9iCleanBlockers` |
| `SabreControlledPnrSellabilityDiagnostics.php` | F9M read-only sellability compose (F9G+F9H+context matrices); lane classifier A–G; optional fresh shop probe helper; normalized RBD/fare-basis comparison. | `inspectBooking`, `probeFreshSellability`, `sameNormalizedStringList` |
| `SabreControlledFinalPnrRetryAllowanceGate.php` | F9Q one-shot explicit final controlled PNR retry allowance after F9P green when F9F+F9J+F9L consumed; F9R post-final-retry host-failure containment + dry-run/confirmed create output alignment (hard-block before live branch). Meta **`controlled_final_pnr_retry_allowance`**. | `evaluateAllowanceEligibility`, `buildAllowanceRecord`, `assessAvailability`, `assessPostFinalRetryContainment`, `applyPostFinalRetryContainmentOutputAlignment`, `recordHostFailureOutcome`, `allows`, `recordUsage`, `isAllowanceValidInMeta` |
| `SabreControlledPnrFinalReadinessDiagnostics.php` | F9P read-only final pre-PNR retry readiness after F9N+F9O (15 min freshness gate); F9Q allowance presence/validity; F9R containment blockers + scalar fields. | `inspectBooking`, `evaluateFinalFreshness` |
| `SabreControlledPnrHostSellabilityEvidenceDiagnostics.php` | F9R read-only local payload + host failure evidence after post-final-retry containment (no supplier HTTP); boolean **`brand_match`** resolution. | `inspectBooking`, `resolveBrandMatch` |
| `SabreControlledPnrStrongRevalidationLinkageDiagnostics.php` | F9O read-only BFM strong revalidation linkage matrix + optional shop probe. | `inspectBooking`, `probeRevalidationLinkage` |
| `SabreControlledStrongRevalidationLinkageApply.php` | F9O controlled strong BFM linkage apply eligibility + safe meta marker (no PNR). F9O-R1: F9O diagnostic source of truth; **`isStaleContextHardBlocker`**. F9P: **`preserveOrInvalidateAfterFreshRerun`**. | `evaluateEligibility`, `isStaleContextHardBlocker`, `applyLinkage`, `preserveOrInvalidateAfterFreshRerun`, `buildApplyRecord` |
| `SabreControlledFreshPnrContextApply.php` | F9N controlled fresh shop context apply eligibility + safe meta marker (no PNR). F9P: **`isFinalFreshnessRerunEligible`**, rerun **`buildApplyRecord`**. | `evaluateEligibility`, `isFinalFreshnessRerunEligible`, `buildProbeFromRefresh`, `buildApplyRecord` |
| `SabrePassengerRecordsApplicationResultDigest.php` | F9G safe ApplicationResults digest for Passenger Records create failures; meta + inspect command; structured excerpts + attempt safe_summary slice. | `digest`, `attemptSafeSummarySlice`, `safeValidationExcerptsStructuredFromDigest`, `hostClassificationContextFromDigest`, `inspectBooking`, `commandSummaryFromDigest` |
| `SabreControlledPnrApprovalOverrideGate.php` | F9D/F9E narrow defer override after F9C approval + F9E fare acceptance when gate active on `controlled_pnr_command` only. | `allowsDeferOverride` |
| `SabreControlledPnrFareChangeAcceptance.php` | F9E operator fare-change acceptance marker for controlled PNR retry (meta only). | `evaluateAcceptanceEligibility`, `buildAcceptanceRecord`, `isAccepted`, `fareChangeGateActive` |
| `SabreOperationalAllowNnStrategyChangedRetryGate.php` | BF7-J-OPS-FIX3: one-shot admin/staff retry after prior NN HaltOnStatus when allow-NN flag ON + operational readiness; loop guard via **`create_halt_on_status_nn_omitted`**. | `allows`, `buildRetryPolicyAuditSlice` |
| `OfferValidationService.php` | Validates selected cached offer + pricing snapshot. | `validateSelectedOffer`, `pricingSnapshotForCachedOffer` |
| `TicketingService.php` | Ticketing eligibility + issue flow; module/env/PNR hard stops + safe logs (9D-4). | `isBookingEligibleForTicketing`, `issueTickets` |

**Al-Haider group ticketing:** **`AlHaiderClient`** (env credentials only — `ALHAIDER_API_USERNAME`/`PASSWORD`; dynamic token cache `alhaider:auth_token` + login lock; **`supplier_auth_token_limit`** fail-closed), **`AlHaiderUmrahGroupService`**, **`AlHaiderPackageNormalizer`**. **GROUP-TICKETING-1** adds local inventory (`GroupInventory`), sync/facet/search services under `app/Services/GroupTicketing/`, public `GroupTicketingSearchController` + `GroupTicketingBookingController` (routes `group-ticketing.*`), admin `AdminGroupTicketingController` + `GroupBookingManagementController`, module `public_umrah_groups`. Legacy `/umrah-groups` redirects to `/groups/search`. **`GroupInventoryCardPresenter`** (`app/Support/GroupTicketing/`) formats public search/detail cards + **`buildCheckoutSummary()`** for shared checkout sidebar. **GROUP-TICKETING-3C / GROUP-REALTIME-INVENTORY-UI-1:** **`GroupInventoryFreshnessService`** (realtime page-1 search refresh, `OTA_GROUP_REALTIME_SEARCH_*`, `sync(forceFresh)` + supplier **data** cache bypass only — not auth token refresh), CLI **`group-ticketing:inspect-provider-payload`**, **`provider:active-auth-audit`**, **`GroupInventoryAvailabilityService`** + **`GroupInventorySyncService::refreshSingle()`** (checkout revalidation). **CHECKOUT-FOUNDATION-1** shared Blade partials under **`resources/views/frontend/checkout/partials/`** (group-first; flight can adopt later). Write API: `AlHaiderClient::reserveGroup` / `cancelReservation` when `ALHAIDER_BOOKING_ENABLED`.

---

## `App\Support\Sabre\SabreLaneRegistry` — architecture lanes (S1A)

Read-only lane map for refactor planning. Does not invoke HTTP or load services.

| Method | Returns |
|--------|---------|
| `all()` | 12 lanes: `core_auth_connection`, `gds_search`, `gds_normalizer`, `gds_revalidation`, `gds_pnr_creation`, `gds_pnr_retrieve_sync`, `gds_cancellation`, `gds_ticketing`, `ndc_reprice_order_change_retrieve`, `ndc_cancel`, `diagnostics_probes`, `experimental_obsolete` |
| `productionCriticalFiles()` | 10 production service paths (client, search, normalizer, revalidate, booking, sync, cancel) |
| `diagnosticsOnlyFiles()` | Inspect/cert/compare files (category `diagnostics`) |
| `obsoleteCandidates()` | `SabreFlightSupplier.php` (placeholder; do not delete yet) |
| `laneForFile($path)` | Primary lane key for a normalized relative path |

## `App\Services\Suppliers\Sabre\Core\SabreEprEncodedCredentials` — OAuth EPR encoding (S3B-1)

Static Sabre REST V2 OAuth Basic credential encoding (EPR + PCC + password). Root path is a **`class_alias`** stub; implementation lives under **`Core\`**.

| Method | Returns |
|--------|---------|
| `encodingStyles()` | List of supported `--encoding-style` values |
| `isValidEncodingStyle($style)` | Whether style is allowed |
| `basicAuthorizationPayload($epr, $pcc, $password, $domainCode)` | Default Sabre triple-base64 Basic payload |
| `basicAuthorizationPayloadForStyle($style, …)` | Payload for a specific encoding style |

Used by **`Core\SabreClient`** (OAuth fallback) and **`SabreCertTokenProbe`** (cert token probe).

## `App\Services\Suppliers\Sabre\Core\SabreClient` — OAuth / search / revalidation HTTP (S3B-2)

Shared Sabre REST client: token, shop search, revalidation POST, authenticated JSON POST. Root path is a **`class_alias`** stub; implementation lives under **`Core\`**.

| Method | Returns |
|--------|---------|
| `resolveEndpointParts($connection, $pathSuffix)` | HTTPS host + path for a REST suffix |
| `httpTimeoutSettings()` | `timeout_seconds`, `connect_timeout_seconds` from config |
| `getAccessToken($connection)` | Cached OAuth bearer (EPR-encoded Basic when explicit `sign_in`/`password`/`pcc`; else client_credentials with EPR fallback on 401) |
| `connectionHasTokenCredentials($connection)` | Whether token material is present |
| `hasExplicitEprCredentialTriple($connection)` | Whether dedicated EPR keys exist in DB credentials |
| `postShopPayload($connection, $payload)` | Authenticated shop POST (inspect tooling) |
| `postRevalidatePayload($connection, $payload, ?$pathOverride)` | Pre-booking revalidation POST |
| `postAuthenticatedJson($connection, $pathSuffix, $json, $extraHeaders)` | Generic authenticated JSON POST |
| `searchFlights($request, $connection)` | BFM shop search |
| `includesPccInShopRequest($connection)` | Whether PCC is sent in shop payload |

Injected by **`SabreFlightSupplierAdapter`**, **`Core\SabreBookingClient`** (OAuth only), **`Gds\SabreSegmentFreshShopSellabilityService`**, diagnostics matrices, and cert/inspect commands.

## `App\Services\Suppliers\Sabre\Gds\SabreFlightSearchRequestBuilder` — BFM shop payload build (S4B-1)

Builds Sabre Offers shop request JSON (minimal BFM v4 production shape + enhanced inspect variant). Root path is a **`class_alias`** stub; implementation lives under **`Gds\`**.

| Method | Returns |
|--------|---------|
| `build($request, $connection)` | Production shop payload |
| `buildInspectShopPayload($request, $connection, $variant)` | `current` or `minimal` inspect payload |
| `includesPccInShopPayload($connection)` | Whether POS.PseudoCityCode is included |
| `payloadStructureSummary($payload)` | Safe structural digest (no PII) |

Injected by **`Core\SabreClient`**, **`Gds\SabreSegmentFreshShopSellabilityService`**, and cert/inspect shop commands.

## `App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder` — BFM revalidate payload build (S4B-2)

Pre-booking revalidation helpers: payload from internal draft, gatekeeper, fare linkage extraction, response digests. Root path is a **`class_alias`** stub; implementation lives under **`Gds\`**.

| Method | Returns |
|--------|---------|
| `buildPayload($draft, ?$style)` | Sanitized revalidation request envelope |
| `assertGatekeeperOrThrow($draft, ?$style)` | Pre-flight gatekeeper (throws on violation) |
| `wireableRequestPayload($payload)` | Strips `_ota_*` keys before HTTP |
| `extractFareLinkage($response)` | Safe fare/linkage map from provider response |
| `linkageDigest($linkage)` | Safe linkage summary flags |
| `structuralPayloadDiagnostics($payload)` | Safe structural digest (no PII) |

Injected by **`Core\SabreClient`**, **`SabreBookingService`**, and cert/inspect revalidate commands.

## `App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest` — stored pricing linkage digest (S4B-3)

Safe scalar digest and readiness assessment for stored normalized offer snapshots (no live Sabre). Root path is a **`class_alias`** stub; implementation lives under **`Gds\`**.

| Method | Returns |
|--------|---------|
| `digest($snapshot)` | Capped scalar digest of shop context / linkage |
| `assessReadiness($snapshot)` | `auto_pnr_pricing_context_ready`, policy, missing fields |
| `assessBfmV4LinkagePolicy($snapshot)` | BFM v4 index/descriptor linkage probe |
| `rebuildSnapshotPricingLinkage($snapshot)` | Restores refs from identifiers/handoff |
| `assessBrandedFareOptionReadiness($option)` | Per branded-fare row readiness |
| `withBookingId($bookingId, $digest)` | Adds booking id to digest |

Used by **`SabreFlightSearchNormalizer`**, **`SabreBookingService`**, **`SabrePnrCertificationSupport`**, and **`sabre:inspect-booking-pricing-context`**.

## `App\Services\Suppliers\Sabre\Gds\SabreSegmentFreshShopSellabilityService` — B77 fresh-shop segment guard (S4B-5)

Per-segment OW Offers shop vs stored snapshot for pre-PNR stale-inventory guard (B76/B77). Root path is a **`class_alias`** stub; implementation lives under **`Gds\`**.

| Method | Returns |
|--------|---------|
| `segmentReportsForOffer($offer, $connection)` | Per-segment fresh-shop reports |
| `segmentPassesPnrFreshShopGuard($segmentReport)` | B77 pass rule (flight + time + optional RBD) |
| `extractStoredSegmentsFromOfferSnapshot($offer)` | Ordered stored segment rows from offer snapshot |
| `marketingFlightLabel($carrier, $flightNum)` | Static marketing flight label helper |

Injected by **`SabreBookingService`**, **`Gds\SabreBookingOfferRefreshService`**, and **`sabre:diagnose-booking-segment-sellability`**.

## `App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService` — C3/C4 offer refresh (S4B-6)

Re-shop stored booking itinerary vs fresh shop; optional **`meta.flight_offer_snapshot`** refresh (admin/local inspect + public checkout pre-PNR). Root path is a **`class_alias`** stub; implementation lives under **`Gds\`**.

| Method | Returns |
|--------|---------|
| `refresh($booking, $apply)` | C3 dry-run or apply refresh payload |
| `validateCurrentSnapshotAgainstFreshItinerary($booking)` | C4 full-itinerary trust check for certification PNR guard fallback |

Injected by **`SabreBookingService`**, **`BookingController`**, and **`sabre:refresh-booking-offer`**. Uses **`Gds\SabreSegmentFreshShopSellabilityService`** for segment extraction/comparison.

## `App\Services\Suppliers\Sabre\PnrRetrieve\` — getBooking retrieve / itinerary sync (S6B)

Trip Orders **`POST /v1/trip/orders/getBooking`** retrieve, map, and sync into **`meta.pnr_itinerary_snapshot`** / **`meta.pnr_itinerary_sync`**. Root paths are **`class_alias`** stubs; implementations live under **`PnrRetrieve\`**.

| Class | Responsibility | Key entry points |
|-------|----------------|------------------|
| `SabrePnrItinerarySyncService` | Production sync orchestrator (module gate, persist meta, **`SupplierBookingAttempt`** `pnr_retrieve`) | `sync($booking, $dryRun)` |
| `SabrePnrRetrieveProbe` | Live getBooking fetch + multi-endpoint CLI probe (shape-tree, map-preview) | `fetchTripOrdersGetBooking`, `probe`, `probeDirectPnr` |
| `SabreTripOrdersGetBookingItineraryMapper` | Pure JSON → preview rows → sanitized snapshot | `mapPreview`, `evaluateSyncEligibility`, `buildSnapshot` |
| `SabreTripOrdersGetBookingInspectSummary` | Safe getBooking status/cancel-flag inference (also consumed by cancel lane) | `buildForProbeRow`, `buildAirlineLocatorObservability`, `extractDirectCancelSafetyFlags`, `buildCancelSchemaInventory` |

Injected by **`HandlesSabrePnrItinerarySync`**, **`sabre:sync-pnr-itinerary`**, **`sabre:inspect-pnr-retrieve`**, **`Cancel\SabreBookingCancelService`**, **`Cancel\SabreCancelBookingInspectProbe`**, **`Diagnostics\SabrePccCapabilityMatrix`**, **`SabrePnrCertificationSupport`**.

## `App\Services\Suppliers\Sabre\Cancel\` — GDS cancellation workflow + inspect (S7B)

Gated Trip Orders **`cancelBooking`** workflow, payload matrix, and cert inspect probe. **`SabreGdsCancelService`** + **`SabreGdsCancelReadiness`** finalize unticketed GDS-only admin cancellation (duplicate protection, post-cancel sync, **`meta.sabre_gds_cancel`**). Lane **`gds_cancellation`** is **`env_gated`** in capability matrix. Root paths are **`class_alias`** stubs; implementations live under **`Cancel\`**.

| Class | Responsibility | Key entry points |
|-------|----------------|------------------|
| `SabreGdsCancelReadiness` | Stored-meta eligibility / admin action state | `evaluate`, `isCancelled`, `isTicketed`, `isCancellationInProgress` |
| `SabreGdsCancelService` | GDS cancel orchestration (lock, sync, persist) | `cancelForBooking` |
| `SabreBookingCancelService` | Portal workflow cancel (pre/post getBooking, env gates) | `cancelForBooking`, `workflowLiveCancelGates`, `preCancelEligibility` |
| `SabreCancelBookingInspectProbe` | Artisan **`sabre:inspect-cancel-booking`** probe (dry-run default) | `inspect`, `inspectDirectPnr`, `mayPerformLiveSabreCancelCall` |
| `SabreCancelPayloadBuilder` | Candidate cancelBooking request bodies / style matrix | `buildCandidatePayloads`, `isDryRunOnlyWrapperStyle`, `isCertifiedGdsConfirmationFullCancelStyle` |
| `SabreCancelBookingContext` | Safe cancel context from booking meta + prior attempts | `fromBooking`, `fromDirectPnr` |
| `SabreTripOrderCancelContext` | Trip-order bookingId/signature from getBooking or meta | `resolve`, `fromGetBookingJson` |
| `SabreCancelProbeDiagnostics` | Sanitized probe diagnostics / stop-probing / support packet | `resolveNextActionRecommendation`, `enrichDigestFromJson` |

Resolves **`Core\SabreClient`**, **`Core\SabreBookingClient`**, and **`PnrRetrieve\`** probe/summary explicitly. Consumers (**`BookingCancellationService`**, **`Booking\SabreBookingService`**, **`Core\SabreBookingClient`**, tests) import root FQCN via stub.

## `App\Services\Suppliers\Sabre\Core\SabreBookingClient` — booking / cancel HTTP (S3B-3)

Low-level Sabre Trip Orders `createBooking` and Passenger Records CPNR HTTP adapter. Root path is a **`class_alias`** stub; implementation lives under **`Core\`**. Uses **`Core\SabreClient`** for OAuth; **`SabreBookingPayloadBuilder`** remains at root.

| Method | Returns |
|--------|---------|
| `createPassengerRecordBooking($connection, $apiEnvelope, $diagnosticsContext, ?$endpointPathOverride)` | Booking POST result + safe `booking_diagnostics` digest |
| `inspectCancelBooking(...)` | Gated cancel inspect probe result (safe digests only) |
| `digestBookingResponseJsonForProbe($json, ...)` | Merged Trip Orders + Passenger Records safe error digest for compare/cert tooling |
| `normalizePassengerRecordsCpnrHttp200Response(...)` | CPNR HTTP 200 normalization (PNR / UC / NN halt paths) |
| `extractPnrLocatorFromBookingJson($json)` | Safe PNR locator extraction |
| `extractSupplierOrderReferenceFromBookingJson($json)` | Safe order/booking id extraction |

Injected by **`SabreBookingService`**, **`Cancel\SabreBookingCancelService`**, **`PnrRetrieve\SabrePnrRetrieveProbe`**, **`Cancel\SabreCancelBookingInspectProbe`**, diagnostics matrices, and **`SabreCertGdsCpnrReportCommand`**.

## `App\Services\Suppliers\Sabre\Core\SabreCapabilityMatrixService` — capability posture (S1B)

Read-only capability matrix. Columns: **`code_implemented`** (`yes`/`no`), **`production`** (`yes`/`env_gated`/`no`), **`live_http`**, **`evidence`** (`certified`/`pending`/`pending_cancel_retrieve_confirmation`/`provider_unsupported_manual`/`n/a`/`disabled`), **`manual`**, **`command`**. Legacy booleans (`production_allowed`, `live_supplier_call_allowed`, `manual_required`, `status`) retained for admin UI. Aligned with **`sabre:prod-gap-audit`** via **`prodGapMatrixKeyMap()`**.

| Method | Returns |
|--------|---------|
| `all()` | 17 capabilities including `gds_ticket_documents`, `gds_void`, `gds_refund`, `ndc_reprice`, `ndc_order_change` |
| `get($key)` | Single capability record or `null` |
| `isEnabled($key)` | `true` when `code_implemented === yes` |
| `requiresManualHandling($key)` | `true` when `manual === yes` |
| `productionAllowed($key)` | `true` when `production === yes` |
| `liveSupplierCallAllowed($key)` | `true` when `live_http === yes` |
| `evidencePending()` | Capabilities with evidence `pending` or `pending_cancel_retrieve_confirmation` |
| `providerUnsupportedManual()` | `gds_void`, `gds_refund` |
| `disabled()` | `code_implemented === no` or evidence `disabled` (currently `ndc_cancel`) |
| `unresolved()` | Alias of `evidencePending()` |
| `prodGapMatrixKeyMap()` | Prod-gap audit key → matrix key |

## `SabreCapabilityPosture` — admin/staff posture surfacing (S1E)

Read-only UI/support wrapper over **`SabreCapabilityMatrixService`**. No env reads, HTTP, or booking behavior changes.

| Method | Returns |
|--------|---------|
| `cancelPosture()` | `gds_cancel` safe summary |
| `ticketingPosture()` | `gds_ticketing` safe summary |
| `ndcPosture()` | NDC keys summary + `summary_label` |
| `diagnosticsPosture()` | `diagnostics` safe summary |
| `summaryForKeys($keys)` | List of posture rows |
| `bookingViewSummary()` | Admin/staff block: labels + `staff_guidance` |
| `architectureDisplayLabel($posture)` | Human label (e.g. unresolved — manual required) |

Wired via **`AdminBookingSupplierActions::sabre_capability_posture`** and **`PnrItinerarySyncSafetyPresenter`** architecture fields on admin/staff booking show.

## `sabre:architecture-report` — architecture posture CLI (S1C)

Read-only SSH-safe report aligned with **`sabre:prod-gap-audit`**. No HTTP, DB, or env secret reads.

| Option | Behavior |
|--------|----------|
| *(none)* | Human-readable tables: lanes, file lists, capability matrix (**Code / Production / Live HTTP / Evidence / Manual / Command**), evidence-pending summary, provider-manual summary, NDC posture, prod-gap alignment counts |
| `--json` | Single JSON document (`report_version` **`sabre_architecture_report_v2`**, `lanes`, file lists, `capabilities`, `evidence_pending_capabilities`, `prod_gap_audit`, …) |

---

## `app/Services/Suppliers/Sabre/` — Sabre-specific

| File | Responsibility | Public API |
|------|----------------|------------|
| `SabreClient.php` | **S3B-2:** **`class_alias`** stub → **`Core\SabreClient`**. | — |
| `SabreBookingClient.php` | **S3B-3:** **`class_alias`** stub → **`Core\SabreBookingClient`**. | — |
| `Core/SabreBookingClient.php` | Low-level Sabre **booking** HTTP; strips `_ota*` envelope keys; Trip Orders POST uses **`tripOrdersFinalWirePostBodyFromEnvelope`** (B28 null strip + flat-wire safe defaults). Trip Orders **200** digest (errors vs PNR vs order id **without PNR → needs_review + provider_booking_id**): codes/messages/field hints/missingFields, `request_id` / `request.correlationId`, `traceId`, `timestamp` (capped excerpts); **B24:** non-success HTTP merges the same capped digest into `booking_diagnostics` incl. `response_top_level_keys`, `response_top_level_error_code` / `response_top_level_type`, `response_additional_messages`; **B27:** `response_error_paths` (pointers / field tokens, capped); **B28:** HTTP 400/422 optional hint when “must not be null” + `wire_payload_null_free` in base log; `sabre_booking_application_error` when errors+no locator; expanded locator/order paths (no raw body); B13 base log carries fare/linkage/commit flags (capped excerpts only). **B38:** **`digestBookingResponseJsonForProbe`**, **`extractPnrLocatorFromBookingJson`**, **`extractSupplierOrderReferenceFromBookingJson`** for endpoint matrix probes. **B44/B45:** **`digestBookingResponseJsonForProbe`** merges Passenger Records **`ApplicationResults` / `CreatePassengerNameRecordRS`** + REST **top-level** `errorCode` / `message` / `status` / `type` (and `timeStamp` presence) with Trip Orders **`errors[]`** parsing (`application_results_status`, `passenger_records_error_digest_present`, `response_timestamp_present`, numeric `errorCode`). Constructor: **`Core\SabreClient`**, `SabreBookingPayloadBuilder`. | `createPassengerRecordBooking` |
| `SabreBookingPayloadBuilder.php` | Internal draft → CPNR-style **or** Trip Orders `createBooking` JSON (`trip_orders_reservation_action`, optional `shop_context`, B13/B14 top-level `fare_linkage` + `pricing.revalidated_total/currency`, segment `fare_basis_code` merged from revalidated linkage); **B22:** nested `createBooking.flightOffer` / `flightDetails`; **B23:** root-wire styles (`*_root_v1`, `trip_orders_product_array_v1`) + `tripOrdersWirePostBodyFromEnvelope`, **`tripOrdersFinalWirePostBodyFromEnvelope` (B28: null strip; safe defaults on flat wire only)**, `summarizeTripOrdersWirePostBody*` (**B24** granular `wire_*` counts + amount/currency flags; products[] `flightDetails` included; **B25** `wire_gender_values_sanitized` + `wire_gender_enum_valid`; **B26** `wire_has_remarks` + `wire_remarks_count`, remarks omitted unless `createbooking_send_remarks`; **B27** `traveler_*` diagnostics, `wire_traveler_required_fields_valid`, `wire_invalid_traveler_field_keys`, Trip Orders name normalization + passport / `document_type_passport_value`; **B28** `wire_null_*`, `wire_payload_null_free`, `wire_contract_valid` / `wire_invalid_contract_keys` for `trip_orders_flight_offer_root_v1`; **B29** `trip_orders_flight_*_camel_v1` maps travelers to camelCase + `wire_traveler_field_style` / `wire_has_givenName` / `wire_has_given_name` + camel root contract validators; **B30** `trip_orders_flight_details_camel_v1` segment contract uses `flightDetails` datetime/airline keys + `wire_segment_field_style` / `wire_segment_required_fields_valid` / `wire_invalid_segment_field_keys`, optional **`trip_orders_flight_details_full_camel_v1`** segment scalars; **B31** `trip_orders_flight_details_sabre_v1` uses **`passengerCode`** + `wire_has_passengerCode` / `wire_has_passengerTypeCode` / `traveler_N_has_passengerCode` + `validateTripOrdersFlightDetailsRootSabreWireContract`; **B32** same style uses root **`contactInfo`** (not `contact`) + `wire_has_contactInfo` / `wire_has_contact` / `wire_contact_field_style` / `wire_has_contact_email` / `wire_has_contact_phone`; **B33** root **`agencyContactInfo`** when `agency_phone` set + **`wire_has_agency_phone`** / **`wire_agency_phone_field_style`** (primary required dot path when satisfied, else `none`) / **`wire_agency_phone_redacted`** / **`wire_has_customer_contact_phone`** / **`wire_agency_phone_ok`** + style **`trip_orders_flight_details_sabre_agency_v1`** + **`sabreTripOrdersTraditionalFlightDetailsStyles`**, **`tripOrdersStyleRequiresSabreAgencyPhone`**, `buildTripOrdersAgencyContactWireBlock`; **B34** compare-only agency-phone shapes (`trip_orders_flight_details_sabre_agencyInfo_v1`, `…_agencyPhoneNumber_v1`, `…_agencyPhonesArray_v1`, `…_rootAgencyPhone_v1`, `…_phoneNumbers_v1`) + **`wire_agency_phone_paths`** + **`expectedSabreAgencyPhoneDotPathsForStyle`**, **`wireHasNonEmptyScalarAtDotPath`**, **`collectWireAgencyPhonePathsPresent`**, **`applyTripOrdersSabreAgencyPhoneForStyle`**); **B35** PNR-style phone rows + **`collectWirePhoneUseTypeLikeValuesSanitized`** + **`wire_phone_use_type_values_sanitized`**; **B36** POS/agency-metadata compare styles (`*_pos_source_phone_v1` incl. optional `POS.Source[].PseudoCityCode` from connection credentials, `*_pos_phone_v1`, `*_agency_root_camel_v1`, `*_travelAgency_v1`, `*_customerInfo_phone_v1`) + `_ota_pcc_available_for_pos` / **`wire_has_POS`** / **`wire_has_pos`** / **`wire_has_agency_block`** / **`wire_has_travelAgency`** / **`wire_has_customerInfo`** / **`wire_pcc_present`** / **`wire_agency_config_phone_present`** / **`wire_agency_country_config_present`** + optional `agency_name` / `agency_city` / `agency_country` / `agency_pos_phone_use_type` config; `redactTripOrdersWireJsonForPreview`; `previewRedactedTripOrdersCreateBookingShape`; **B25:** `mapToSabreTripOrdersGenderEnum`, `sabreTripOrdersGenderEnumAccepted`, Trip Orders traveler gender enums + CPNR `M`/`F` mapping; **B26:** `tripOrdersWireRemarksEnabled`, `buildTripOrdersWireRemarks`; **no** payment capture / ticket issue; `summarizeEnvelopeForDiagnostics` includes `wire_*` + `inspect_warning_trip_orders_wire_root` + **`inspect_warning_wire_root_incomplete`**. **B44:** traditional **`buildSabreApiEnvelope`** adds **`AirBook.HaltOnStatus`**, segment **`Status`/`Number`**, **`PostProcessing.EndTransaction`** (minimal **`Source.ReceivedFrom`**) + **`PostProcessing.RedisplayReservation.waitInterval`** (**B52:** no **`EndTransactionRQ`**), **`AddRemark`** remarks with **`Type: General`** (**B53**, not **`GENERAL`**). **B54:** omits **`SpecialReqDetails.SpecialService`** (TTL → **`General`** remark); wire summary **`wire_special_service_*`**, **`wire_add_remark_present`**; augment strips **`SpecialService`**. **B55:** no **`AgencyInfo.Telephone`**; augment strips **`Telephone`**; wire **`wire_agency_info_*`**, **`wire_customer_info_has_contact_numbers`**, **`wire_customer_info_has_email`**. **B56:** **`CustomerInfo.PersonName`** is a JSON array (list) of rows; augment normalizes object → one-item list; wire **`wire_customer_person_name_*`**. **B58:** **`CustomerInfo.Email`** rows are a JSON list with **`Type=TO`** per non-empty **`Address`** (`traditionalPnrNormalizeCustomerInfoEmailForTraditionalPnr`, **`wire_customer_email_*`** diagnostics, **`redactWireValueForPreview`** redacts string **`Address`**). **B59:** **`traditionalPnrNormalizeRootAirPricePassengerTypeQualifiers`** adds root **`AirPrice[0].PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType`** (string **`Quantity`**, **`CHD`→`CNN`** here only, no **`Brand`**); **`summarizeTraditionalPnrWirePostBody`** **`wire_air_price_*`**, **`wire_air_price_passenger_type_contract_valid`**, **`wire_iati_airprice_passenger_type_delta_closed`**. **B61/B61A/B61B:** gated **`AirBook.RetryRebook`** (**`Option=true`** boolean) + **`AirBook.RedisplayReservation`** with **integer** **`NumAttempts`/`WaitInterval`** when **`suppliers.sabre.traditional_cpnr_airbook_retry_redisplay`**; wire **`wire_airbook_retry_redisplay_enabled`**, **`wire_airbook_has_retry_rebook`**, **`wire_airbook_has_redisplay_reservation`**, **`wire_airbook_retry_rebook_has_option`**, **`wire_airbook_retry_rebook_option_type`**, **`wire_airbook_retry_rebook_contract_valid`**, **`wire_airbook_*_type`**, **`wire_airbook_retry_redisplay_numeric_contract_valid`**, four **`wire_airbook_*_present`** (integers only on numeric fields). **B47:** CPNR **`AirBook`** is sell-only (no nested **`AirPrice`** / **`OTAFareBreakdownSummary`** / **`PriceQuoteInformation`**); root **`AirPrice.PriceRequestInformation.Retain`**; wire **`wire_airbook_has_*`**, **`wire_has_root_air_price`**. | `buildInternalDraft`, `buildSabreApiEnvelope`, `buildTripOrdersCreateBookingEnvelope`, `resolveCreatebookingPayloadStyle`, `mapToSabreTripOrdersGenderEnum`, `tripOrdersStyleUsesRootWireBody`, `tripOrdersWirePostBodyFromEnvelope`, **`tripOrdersFinalWirePostBodyFromEnvelope`**, `summarizeTripOrdersWirePostBody`, `summarizeTripOrdersWirePostBodyForEnvelope`, `redactTripOrdersWireJsonForPreview`, `previewRedactedTripOrdersCreateBookingShape`, `summarizeEnvelopeForDiagnostics`, `summarizeTripOrdersEnvelopeForDiagnostics`, **`detectWireSegmentFieldStyle`**, **`mapTripOrdersTravelersWireToSabreTripOrdersCamelCase`**, **`tripOrdersStyleUsesSabreTripOrdersPassengerCode`**, **`tripOrdersStyleUsesSabreTripOrdersContactInfo`**, **`sabreTripOrdersTraditionalFlightDetailsStyles`**, **`tripOrdersStyleRequiresSabreAgencyPhone`**, **`expectedSabreAgencyPhoneDotPathsForStyle`**, **`wireHasNonEmptyScalarAtDotPath`**, **`collectWireAgencyPhonePathsPresent`**, **`collectWirePhoneUseTypeLikeValuesSanitized`**, **`resolveSabrePseudoCityCodeForTripOrdersWire`** |
| `SabreRevalidationPayloadBuilder.php` | **S4B-2:** **`class_alias`** stub → **`Gds\SabreRevalidationPayloadBuilder`**. | — |
| `Gds/SabreRevalidationPayloadBuilder.php` | B14–B22 + **production revalidate rules**: payload from draft + BFM context/GIR archive enrichment; **`assertGatekeeperOrThrow`**, **`evaluateGroupedItineraryMessages`**, **`assertPerSegmentFareBasisComplete`**, **`evaluateRevalidationPricingTripwire`**, **`revalidationPayloadFreezeFingerprint`**, **`enrichInternalDraftFromGirArchive`**; styles incl. **`client_gds_revalidate_v1`**, **`iati_like_bfm_revalidate_v1`**, etc.; **B20** response digests + expanded `extractFareLinkage` / `linkageDigest` (per-segment fare basis flags). | `buildPayload`, `assertGatekeeperOrThrow`, `evaluateGroupedItineraryMessages`, `assertPerSegmentFareBasisComplete`, `evaluateRevalidationPricingTripwire`, `enrichInternalDraftFromGirArchive`, `wireableRequestPayload`, `extractFareLinkage`, `linkageDigest`, `structuralPayloadDiagnostics` |
| `SabreStoredPricingContextDigest.php` | **S4B-3:** **`class_alias`** stub → **`Gds\SabreStoredPricingContextDigest`**. | — |
| `Gds/SabreStoredPricingContextDigest.php` | B16/C5/11G: safe scalar digest + **`assessReadiness`** (`auto_pnr_pricing_context_ready`, `pricing_context_policy`, BFM index/itinerary policy, formal vs BFM missing fields; no raw body). | `digest`, `assessReadiness`, `assessBfmV4LinkagePolicy`, `rebuildSnapshotPricingLinkage`, `withBookingId` |
| `SabreTraditionalCpnrIatiWireStructureDiagnostic.php` | **2026-05-15:** Frozen IATI GDS `CreatePassengerNameRecordRQ` key scaffold vs OTA wire — **dot key paths only** (no PII); documents default **`/v2.5.0/passenger/records?mode=create`** vs IATI **`/v2.4.0/...`**; **`EnhancedAirBook`** absent on both. **B57:** **`cpnrKeyNameInventory`** (PersonName/Email/FlightSegment/AirPrice key unions), **`b57_host_warning_correlation`** notes in **`analyze`**. **B61/B61A/B61B:** when **`suppliers.sabre.traditional_cpnr_airbook_retry_redisplay`** is on and OTA wire carries **`RetryRebook.Option=true`** (boolean) + **integer** **`NumAttempts`/`WaitInterval`** on both AirBook helper blocks, **`key_paths_only_in_iati_template`** drops **`AirBook.RetryRebook*`** / **`AirBook.RedisplayReservation*`** prefixes; **`b61_*`** flags + **`key_paths_only_in_iati_template_unadjusted_count`**. | `analyze`, `collectStructuralKeyPaths`, `iatiOperationalGdsCpnrKeyTemplate`, `cpnrKeyNameInventory` |
| `SabreBookingService.php` | Full Sabre booking orchestration (schema selection, gates, validation, **B13/B20 pre-`createBooking` revalidation** with `_fare_linkage` merge, **B21 opt-in bypass** when revalidation fails or is disabled — audited logs + `safe_summary` keys, `response_structure` + granular `reason_code`, `safe_summary.revalidation_reason_code` on revalidate failure, short-circuit on failure when bypass off, create/hold, admin supplier booking, PNR persistence, public checkout storage, retrieve/cancel/ticket, dry-run, MANDATORY_DATA_MISSING safe_summary linkage + **B23 wire** flags + **B24** compare-send attempt + JSON report helpers; **B27** Trip Orders traveler pre-live guard + **`B28`** null-free + `wire_contract_valid` guard + `payload_validation_failed` + `tripOrdersTravelerPayloadAuditSlice`; **B29** `wire_traveler_field_style` / `wire_has_givenName` / `wire_has_given_name` on compare rows, attempts, `flattenBookingDiagnostics`, linkage slices; **B31** `wire_has_passengerCode` / `wire_has_passengerTypeCode`; **B32** `wire_has_contactInfo` / `wire_has_contact` / `wire_contact_field_style` / `wire_has_contact_email` / `wire_has_contact_phone`; **B33** `wire_has_agency_phone` / `wire_agency_phone_field_style` / `wire_agency_phone_redacted` / `wire_has_customer_contact_phone` / `wire_agency_phone_ok` + pre-live **`agency_phone_missing`** guard + compare `--send` same; **B34** `wire_agency_phone_paths` on preview/compare/flatten/audit slices; **B35** `wire_phone_use_type_values_sanitized` same surfaces; **B36** `wire_has_POS` / `wire_has_pos` / `wire_has_agency_block` / `wire_has_travelAgency` / `wire_has_customerInfo` / `wire_pcc_present` / `wire_agency_config_phone_present` / `wire_agency_country_config_present` same surfaces). **B40:** `bookingCapabilityReportForCommand()` (no live HTTP), `countAgencyPhoneBodyVariantFailuresForBooking()`, **`compare_booking_endpoint`** attempt logging from **`compareBookingEndpointsForCommand --send`**, expanded agency-phone **`safe_summary`** classifier keys. **B41:** capability report **`traditional_pnr_preview_valid`** from **`previewTripOrdersWireJsonForInspectCommand`** traditional branch; entitlement rollup **`unknown_not_tested_after_b40`** / counts / conditional **`recommended_next_action`**. **B42:** **`discoverBookingEndpointsProbeForConnection()`** + **`bookingEndpointDiscoveryProbePaths()`** + discovery rollups / SOAP hint helpers; capability report merges **`expanded_endpoint_discovery_summary`** when JSON discovery file exists. **B43:** Passenger Records **`?mode=create`** / **`?mode=update`** probes + compare allowlist (query preserved); entitlement keys for three **`mode=create`** paths; **`recommended_next_action`** when **`/v2.5.0/passenger/records?mode=create`** last compare is non-403. **B44/B45:** **`compareBookingEndpointsForCommand --send`** merges **`digestBookingResponseJsonForProbe`** Passenger Records **`ApplicationResults`** + REST top-level errors into compare rows + **`supplier_booking_attempts.safe_summary`** (`response_top_level_*`, `response_timestamp_present`, `request_body_non_empty`, `wire_has_create_passenger_name_record_rq`); **`Conversation-ID`** header on **`passenger/records`** POSTs. | See **detailed list** below |
| `SabreFlightSearchRequestBuilder.php` | **S4B-1:** **`class_alias`** stub → **`Gds\SabreFlightSearchRequestBuilder`**. | — |
| `Gds/SabreFlightSearchRequestBuilder.php` | Builds Sabre shop request payloads (BFM v4 minimal + enhanced inspect variant). | `build`, `buildInspectShopPayload`, `includesPccInShopPayload`, `payloadStructureSummary` |
| `SabreFlightSearchNormalizer.php` | **S4B-4:** **`class_alias`** stub → **`Gds\SabreFlightSearchNormalizer`**. | — |
| `Gds/SabreFlightSearchNormalizer.php` | Normalizes Sabre search JSON → app offer shape; merges fare-component booking class / fare basis onto segments; stores `raw_payload.sabre_fare_excerpt`, `sabre_shop_identifiers`, `sabre_shop_context`, server-only **`sabre_bfm_gir_archive`** (`buildSabreBfmGirArchiveSlice`); **L2C** display fare reconciliation. | `normalize`, `buildSabreBfmGirArchiveSlice`, `inspectRawItineraryDigests`, `extractFareBreakdownFromFare`, `reconcileSabreDisplayFareComponents` |
| `SabreDiagnoseCpnrVsIatiStructureCommand.php` | **2026-05-15** local/testing Artisan: OTA traditional CPNR wire vs frozen IATI GDS key-path diff (`--booking=`); JSON only; no HTTP. | `sabre:diagnose-cpnr-vs-iati-structure` |
| `SabreInspectBookingPayloadCommand.php` | Local/testing Artisan: sanitized booking-payload shape for a booking id (B13 linkage flags; **B22** `--preview-json` redacted shape; **B23** `--wire-preview-json`, `--write-wire-preview=`, `--style=`; **B25** wire meta `wire_gender_*`; **B26** `wire_has_remarks` / `wire_remarks_count`; **B27** `traveler_*` / `wire_traveler_required_fields_valid` in wire preview; **B28** `wire_null_*`, `wire_payload_null_free`, `wire_contract_valid` / `wire_invalid_contract_keys` in wire preview; **B31** `wire_has_passengerCode` / `wire_has_passengerTypeCode` / `traveler_N_has_passengerCode` when using sabre traveler style; **B32** `wire_has_contactInfo` / `wire_contact_field_style` / `wire_has_contact_email` / `wire_has_contact_phone` for sabre_v1; **B33** `wire_has_agency_phone` / `wire_agency_phone_field_style` / `wire_agency_phone_redacted` / `wire_has_customer_contact_phone` / `wire_agency_phone_ok`; **B34** `wire_agency_phone_paths`; **B35** `wire_phone_use_type_values_sanitized`; **B36** `wire_has_POS` / `wire_has_pos` / `wire_has_agency_block` / `wire_has_travelAgency` / `wire_has_customerInfo` / `wire_pcc_present` / `wire_agency_config_phone_present` / `wire_agency_country_config_present`). **B75:** `--segment-sell-diagnostics` (`--note=`) prints **`segment_sell_diagnostics_json=`** (CPNR AirBook segment sell triage; combine with `--wire-preview-json` as needed). **B78:** `--fare-context-diagnostics` prints **`fare_context_diagnostics_json=`** (snapshot fare/VC/pricing vs root `AirPrice` qualifiers; no CPNR POST). | `sabre:inspect-booking-payload` |
| `SabreCompareCreatebookingStylesCommand.php` | **B22/B23/B24** local/testing Artisan: compares Trip Orders createBooking payload styles (shape + `wire_*`; optional **`--send`** requires **`--style`** or **`--send-all`**; optional **`--style=`** for a single allowed style); invalid `--style` exits with allowed list. **B40:** warns when five+ agency-variant failures already stored and another agency-variant **`--send`** runs. | `sabre:compare-createbooking-styles` |
| `SabreBookingCapabilityReportCommand.php` | **B40/B41/B42** local/testing Artisan: Trip Orders vs traditional capability snapshot from booking + stored attempts (no live HTTP); B41 aligns **`traditional_pnr_preview_valid`** with **`previewTripOrdersWireJsonForInspectCommand`** traditional style; entitlement unknown token **`unknown_not_tested_after_b40`**; prints **`traditional_pnr_*` counts**, **`local_agency_phone_hints_found`**; **B42:** prints **`expanded_endpoint_discovery_summary`** JSON when present (from **`storage/app/sabre-booking-endpoint-discovery.json`**). | `sabre:booking-capability-report` |
| `SabreTicketingCapabilityReportCommand.php` | **T2** local/testing inspect-only: Sabre ticketing readiness snapshot per booking (E10 **`TicketingReadinessPresenter`**, PNR sync sidecar, ticket/attempt counts, config flags, static endpoint candidates, **`recommended_next_action`**); **`--json`** safe line; no HTTP/DB writes; adapter remains **`not_supported`**. | `sabre:ticketing-capability-report` |
| `SabreDiscoverTicketingEndpointsCommand.php` | **T2B** local/testing ticketing REST discovery matrix; default inspect-only; **`--send`** safe entitlement probes via **`SabreTicketingEndpointDiscovery`** (no issue/FOP/cancel/void); **`--json`**, **`--max-calls`**, **`--path`**, optional **`--output=`**. | `sabre:discover-ticketing-endpoints` |
| `Diagnostics/SabrePccCapabilityMatrix.php` | **Q1 / S2H** PCC/credential capability matrix builder (+ co-located **`SabrePccCapabilityCallBudget`**): auth/shop/CPNR/Trip Orders/PNR read/ticketing discovery sections; **`classifyMatrixAccessResult()`** semantic overrides; shared **`--max-calls`** budget; redacted rows only. Old path is dual S2H **`class_alias`** stub. | (service; used by command) |
| `Diagnostics/SabreCertEntitlementMatrix.php` | **CERT** REST entitlement matrix builder: curated CERT endpoints (shop/revalidate/CPNR/Trip Orders/NDC); empty `{}` probes only; reuses **`SabrePccCapabilityMatrix::classifyMatrixAccessResult()`** (same Diagnostics namespace). Old path is S2F **`class_alias`** stub. | (service; used by command) |
| `SabreCertGdsRevalidateMatrixCommand.php` | **CERT** GDS revalidation path/style matrix; live shop + capped grid; **`--paths`**, **`--styles`**, **`--max-attempts`**, **`--stop-on-success`**, **`--json`**, **`--output=`**, **`--log`**. Gate: **`certEntitlementMatrixSendAllowed()`** + CERT host only. | `sabre:cert-gds-revalidate-matrix` |
| `SabreCertGdsCpnrReportCommand.php` | **CERT** GDS CPNR wire preview (default) or gated send (**`--send --confirm-cert-pnr-send=YES`**); live **`/v4/offers/shop`** + redacted Passenger Records payload; **`pricing_diagnostics`** (incl. scalar **`wire_airprice_*`** for D2E flag verification), **`style_comparison`**, **`send_gate_summary`**. Send scenario A: **`ow_direct`** single-segment + any **`CERTIFIED_SEND_COMBINATIONS`** pair. Send scenario B: PK same-carrier **`ow_connecting`** 2-segment + **iati v2.4 only**. Send scenario C: QR same-carrier **`ow_connecting`** 2-segment + **iati v2.4 only** (GF/other blocked). **`--allow-nn-cert-diagnostic=YES`**: PK or QR 2-segment only. **`SabreBookingClient::createPassengerRecordBooking`** (no Booking row). Gate: CERT host + ticketing off. | `sabre:cert-gds-cpnr-report` |
| `SabreCertGdsLinkageReportCommand.php` | **CERT** GDS shop linkage-readiness report; live **`/v4/offers/shop`**; **`--scenario`**, **`--return-date`**, **`--limit`**, **`--json`**, **`--output=`**, **`--log`**. Gate: **`certEntitlementMatrixSendAllowed()`** + CERT host only (blocks live). | `sabre:cert-gds-linkage-report` |
| `SabreCertEntitlementMatrixCommand.php` | **CERT** SSH-only Artisan: entitlement matrix inspect default; **`--send`** capped probes; **`--connection`**, **`--json`**, **`--output=`**, **`--log`**. Production gated via **`SabreInspectGate::certEntitlementMatrixAllowed()`**. | `sabre:cert-entitlement-matrix` |
| `SabrePccCapabilityMatrixCommand.php` | **Q1** local/testing Artisan: full PCC matrix inspect default; **`--send`** safe probes; **`--booking`**, **`--connection`**, **`--json`**, **`--output=`**. | `sabre:pcc-capability-matrix` |
| `Diagnostics/SabreTicketingEndpointDiscovery.php` | **T2B / S2D** candidate matrix + optional live probes (diagnostics namespace); reuses **`SabreBookingService::discoveryAccessResultForProbe`** / **`SabreBookingClient::digestBookingResponseJsonForProbe`**; classifies **`not_authorized`**, **`excluded_destructive`**, **`not_probed`**. Old path is **`class_alias`** stub. | (service; used by discover command + PCC matrix) |
| `SabreDiscoverBookingEndpointsCommand.php` | **B42** local/testing Artisan: expanded REST booking/PNR path matrix; POST **`{}`** only; optional **`--write-report=`** safe JSON; SOAP hint when PNR-family paths blocked. **B43:** probe list includes Passenger Records **`?mode=create`** / **`?mode=update`** candidates; **`discoveryEndpointFlags()`** classifies query modes. | `sabre:discover-booking-endpoints` |
| `SabreInspectBookingAttemptCommand.php` | Local/testing Artisan: safe `supplier_booking_attempts` row summary (no raw payload); **B24:** `payload_style`, `action`, wire segment/traveler counts, response top-level keys + typed error codes/messages. | `sabre:inspect-booking-attempt` |
| `SabreInspectBookingRevalidateCommand.php` | B14–B20 local/testing Artisan: sanitized revalidate summary; `--send` live HTTP; `--style=`; **`--path=`**; **`--preview-json`** / **`--write-preview=`**; `payload_diagnostics.*`; `--send` prints `diag.*` + **`response_structure.*` when HTTP 2xx and revalidation_success=false** + optional `contract_hint`; HTTP 2xx `--send` persists `meta.sabre_revalidate_inspect`. | `sabre:inspect-booking-revalidate` |
| `SabreInspectBookingPricingContextCommand.php` | B16 local/testing Artisan: stored-offer pricing context digest only (no HTTP). | `sabre:inspect-booking-pricing-context` |
| `SabreAuditBookingContinuityCommand.php` | 11K-B local/testing Artisan: passive booking continuity audit (snapshot → context → revalidation draft → PNR draft); no live HTTP/`--send`. | `sabre:audit-booking-continuity` |
| `SabreBookingContinuityAuditor.php` | 11K-B/C2: `audit(Booking)` → safe continuity rows + `readiness_recommendation` + host outcome overlay (`host_rejection_evidence_present`, `CERTIFIED_ROUTE_PENDING` for internal gate); evidence-based host rejection only; uses digest + revalidation payload summary + PNR segment sell diagnostics (no behavior change). | (service; invoked by command/tests) |
| `SabreInspectBookingConfigCommand.php` | B21/B36/B38 local/testing Artisan: booking id → endpoint host/path, booking/ticketing/revalidate/bypass flags, `can_attempt_createbooking_now` (live gates + create-without-revalidation path), `createbooking_without_revalidation_allowed`, `reason_if_blocked`, segment/passenger counts, `has_*` booleans, `validation_ok` (no PII); **B36** `active_createbooking_payload_style`, `booking_path`, `agency_phone_config_present`, `agency_country_config_present`, `pcc_present` (booleans only); **B38** `trip_orders_agency_phone_still_rejected`, `suggested_booking_flow`, `agency_phone_profile_hint` (no PCC/phone values). | `sabre:inspect-booking-config` |
| `SabreCheckBookingEndpointsCommand.php` | Local/testing Artisan: OAuth once, then POST `{}` to deduped set: configured `booking_path`, `/v2/passengers/create`, **`/v2/passenger/create`**, v2.4/v2.5 passenger/records, `/v1/trip/orders*` probes; prints `label`, `endpoint_path`, `method`, `http_status`, `available`, `ready`, `access_result` (no bodies/tokens). | `sabre:check-booking-endpoints` |
| `SabreCompareBookingEndpointsCommand.php` | **B38** local/testing Artisan: endpoint × payload-style matrix (inspect-only default; optional single **`--send`** with explicit **`--endpoint`** + **`--style`**; **`--skip-trip-orders`**). **B43:** **`--endpoint`** may include **`?mode=create`** (full suffix preserved on URL + attempt `endpoint_path`). **B44:** **`--send`** rows echo merged Passenger Records digests + request-body wire diagnostics (from service). | `sabre:compare-booking-endpoints` |
| `SabreCheckRevalidateEndpointsCommand.php` | B18 local/testing Artisan: OAuth once, POST `{}` to configured `revalidate_path` + common revalidate/shop/fares candidates + `createBooking` reference row; prints `access_result` incl. `timeout` / `reachable_validation_error` (400/422); capped safe error code/message only. | `sabre:check-revalidate-endpoints` |
| `SabreCompareRevalidateStylesCommand.php` | B19–B20 local/testing Artisan: matrix `/v4/offers/shop/revalidate` + `/v5/offers/shop/revalidate` × four payload styles; baseline `/v4/shop/flights/revalidate`; safe table + optional `recommended_revalidate_path`; **`--show-response-digest`** adds digest columns (no raw body). | `sabre:compare-revalidate-styles` |
| `SabreCompareRevalidateEndpointsCommand.php` | B22 local/testing Artisan: expanded path × style matrix, scoring, capped recommendations, optional JSON digest report (`--write-report=`); **`--max-calls`** caps live revalidate POSTs; **`--connection=`** forces a Sabre `supplier_connection` id (agency must match booking). | `sabre:compare-revalidate-endpoints` |
| Other `Sabre*.php` | Inspect, credentials, inspect gates/sanitizers, etc. | *(add rows here when those files become frequent edit targets)* |

### `SabreBookingService.php` — public methods (large file)

- `isBookingEnabled`, `isBookingLiveCallEnabled`, `isTicketingEnabled`, `isRevalidationBeforeBookingEnabled`, **`isAllowCreateBookingWithoutRevalidation` (B21)**
- `mayPerformLiveSabreBookingCall`
- `effectiveSabreBookingSchema`
- `runRevalidationBeforeBooking` (B13/B16/B18/B20 — optional `$payloadStyle` + optional `$pathOverride` for inspect only; `createBooking` uses config path; returns `endpoint_path`, `wire_root_keys`, 27131 heuristics, `response_structure`, `reason_code` incl. empty/unusable + application-warning branches, `error_digest` on non-2xx / HTTP-200 warnings)
- `canBookOffer`, `prepareBookingPayload`, `createBooking`, `createPnrHold`
- `finalizePublicCheckoutSabreStorage`, `persistLiveSabrePnrOnBooking`, `runPublicReviewDryRun`, `inspectBookingPayloadShapeForCommand`, **`previewRedactedTripOrdersCreateBookingForCommand(?$payloadStyleOverride)`**, **`previewTripOrdersWireJsonForInspectCommand` (B23 final wire / B28 diagnostics; B39/B41 traditional CPNR branch + `summarizeTraditionalPnrWirePostBody`; B43 extra traditional `wire_has_*` flags)**, **`inspectPassengerRecordsAirBookSegmentSellDiagnosticsForCommand` (B75 — safe Passenger Records **AirBook** segment sell JSON + route/chronology + last attempt; local/testing; no live POST)**, **`inspectPassengerRecordsFareContextDiagnosticsForCommand` (B78 — fare/pricing/carrier snapshot + root `AirPrice` optional-qualifier keys; `last_supplier_attempt_error` for *NO FARES-class host text; no CPNR POST)**, **`previewRedactedTraditionalPnrForCommand` (B38 redacted CPNR-style inspect; no `wire_*` contract — use wire inspect for contract)**, **`inspectTraditionalCpnrIatiStructureDiffForCommand` (2026-05-15: CPNR vs frozen IATI GDS key-path diff + `ota_wire_contract_summary`; no HTTP)**, **`compareTripOrdersCreateBookingStylesForCommand` (B22/B23; B24: `--send` persists compare attempts + JSON report via command + service helpers; B28 compare-send respects null-free + contract guard; B38/B40 agency-phone classifier keys + B40 blind-variant warning on rows)**, **`compareBookingEndpointsForCommand` (B38 matrix / single-send probe; B40 persists `compare_booking_endpoint` attempts; B43 query-string endpoints; B44 Passenger Records digest merge + request-body diagnostics + Conversation-ID on passenger/records POSTs)**, **`bookingCapabilityReportForCommand` (B40 no-live summary; B41 traditional preview + entitlement rollup + counts; B42 merges `expanded_endpoint_discovery_summary` when `storage/app/sabre-booking-endpoint-discovery.json` exists; B43 `?mode=create` paths + conditional `recommended_next_action`)**, **`discoverBookingEndpointsProbeForConnection`**, **`bookingEndpointDiscoveryProbePaths`**, **`discoveryAccessResultForProbe`**, **`expandedEndpointDiscoverySummaryFromRows`**, **`expandedEndpointDiscoverySummaryFromStoredReportPath`**, **`buildBookingEndpointDiscoveryReportPayload`**, **`discoveryShouldEmitSoapHint`**, **`discoverySoapHintMessage`**, **`discoveryEndpointFlags`**, **`countAgencyPhoneBodyVariantFailuresForBooking` (B40)**
- `retrieveBooking`, **`cancelBookingForBooking` (3G-Cancel-R1)**, `cancelBooking` (resolves booking ref/PNR/id → `cancelBookingForBooking`), `issueTicket`
- `validateNormalizedSabreOffer`, `revalidateOffer`, `createPassengerRecord`
- `createSupplierBooking` (admin path → `SupplierBookingResultData`)

---

## `app/Services/Suppliers/Duffel/`

| File | Responsibility | Public API |
|------|----------------|------------|
| `DuffelClient.php` | Duffel REST: offer requests, list/get offers, create/get orders. | `createOfferRequest`, `getOfferRequest`, `listOffers`, `getOffer`, `createOrder`, `getOrder` |
| `DuffelBookingService.php` | Wraps order creation for admin supplier booking path. | `createSupplierBooking` |
| `DuffelOfferNormalizer.php` | Search/offer JSON → normalized offer shape. | *(grep `public function` if extending)* |
| Other `Duffel*.php` | Normalizers, safe summaries, etc. | Add a row here when a file becomes a frequent edit target. |

---

## `app/Services/Suppliers/Iati/`

| File | Responsibility | Public API |
|------|----------------|------------|
| `IatiClient.php` | Central HTTP for flight v2 + structured logging (`storage/logs/iati.log`). JWT for flight calls when secret set; auth_code for ping. | `post`, `get`, `send`, `unwrapResult` |
| `IatiAuthService.php` | JWT via `/rest/auth/token` when secret stored; raw auth_code for ping and when no secret. | `getBearerToken`, `getPingBearerToken`, `usesJwtExchange`, `getToken`, `clearTokenCache` |
| `IatiConfigResolver.php` | TEST/PROD base URLs from `SupplierConnection`; `organization_id` optional. | `resolve`, `isTestEnvironment` |
| `IatiPayloadBuilder.php` | Search/fare/book/contact/passenger payloads. | `buildSearchPayload`, `buildFarePayload`, `buildBookPayload`, … |
| `IatiPassengerNormalizer.php` | Maps `booking_passengers` (`first_name`, `last_name`, `passenger_type`, …) to IATI supplier passenger DTO + missing-field diagnostics (`passengers.N.given_name`, etc.). | `normalize`, `missingSupplierFieldsForBooking`, `assertBookingPassengersReady` |
| `IatiResponseNormalizer.php` | Search/fare/book/retrieve/cancel → OTA DTOs + `provider_context`; multi-fare `branded_fares` (2+), segment enrichment, `customer_display_fields`. | `normalizeSearchResponse`, `normalizeFareResponse`, … |
| `IatiFlightSearchService.php` | Search orchestration. | `search` |
| `IatiFareRevalidationService.php` | Non-mutating `POST /fare` before checkout; public report + branded fare_key swap. | `revalidate`, `buildPublicRevalidationReport`, `auditLinkageFromOffer` |
| `IatiSelectedOfferRevalidationGate.php` | Public selected-offer IATI revalidation gate (no book/ticket). | `refreshSelectedOffer` |
| `IatiBookingService.php` | Option/book + duplicate guards. | `createSupplierBooking` |
| `IatiTicketingService.php` | `POST /option/{id}/book` (no `/ticket`). | `issueTickets` |
| `IatiRetrieveService.php` | `GET /order/{id}` sync. | `syncBooking` |
| `IatiCancelService.php` | `GET /book|option/{id}/cancel`. | `cancelForBooking` |
| `IatiBalanceService.php` | Balance probe (graceful if unsupported). | `checkBalance` |
| `Adapters/IatiFlightSupplierAdapter.php` | `FlightSupplierInterface`. | `search`, `validateOffer` |
| `BookingAdapters/IatiSupplierBookingAdapter.php` | `SupplierBookingInterface`. | `createSupplierBooking` |
| `TicketingAdapters/IatiSupplierTicketingAdapter.php` | `SupplierTicketingInterface`. | `issueTickets` |

---

## `app/Services/Suppliers/PiaNdc/`

| File | Responsibility | Public API |
|------|----------------|------------|
| `PiaNdcClient.php` | Central SOAP HTTP client; normalizes Crane NDC SOAPAction to quoted **`cranendc/{action}`**; failed calls → **`warning`** on **`pia-ndc`** log. | `call` |
| `PiaNdcConfigResolver.php` | Endpoint/credentials/party/contact email from `SupplierConnection`. | `resolve`, `resolveForTicketing` |
| `PiaNdcXmlBuilder.php` | SOAP request payloads (AirShopping, **OfferPrice**, OrderCreate, GeneralParams, AirlineProfile, cancel preview variants R11F, …). | `buildAirShoppingRequest`, `buildOfferPriceRequest`, `buildGeneralParamsRequest`, … |
| `PiaNdcXmlParser.php` | Namespace-aware response parse + faults/errors + Hitit AirShopping/OfferPrice (`CarrierOffers`, `PricedOffer`, journey refs, baggage lists). | `parse`, `countNodesByLocalName` |
| `PiaNdcResponseNormalizer.php` | Search/**offer price**/book/ticket/cancel → OTA DTOs + `provider_context`; Hitit journey/segment/baggage mapping. | `normalizeSearchResponse`, `normalizeOfferPriceResponse`, … |
| `PiaNdcFlightSearchService.php` | DoAirShopping orchestration; CLI **`runAirShoppingDiagnostic`**; smart no-fares warnings. | `search`, `runAirShoppingDiagnostic` |
| `PiaNdcProfileService.php` | DoGeneralParams + DoAirlineProfile diagnostics. | `fetchProfile` |
| `PiaNdcDiagnosticService.php` | Credential/endpoint health + auth header names. | `healthCheck` |
| `PiaNdcOfferPriceService.php` | DoOfferPrice CLI diagnostic (`runOfferPriceDiagnostic`); public **`revalidate`** deferred no-op. | `revalidate`, `runOfferPriceDiagnostic` |
| `PiaNdcBookingService.php` | DoOrderCreate option PNR + duplicate guards; CLI **`runOrderCreateDiagnostic`** (dry-run default). | `createSupplierBooking`, `runOrderCreateDiagnostic` |
| `PiaNdcOptionPnrService.php` | Shared unpaid option PNR create (public auto + legacy admin route); **`auto_create_option_pnr`** audit action. | `autoCreateOptionPnrForPublicBooking`, `createOptionPnrForBooking`, `evaluateCreateEligibility` |
| `PiaNdcBookingProviderContextResolver.php` (`App\Support\Bookings`) | Resolves OrderCreate `provider_context` from booking meta snapshots and hold sessions. | `resolve`, `hasResolvableContext` |
| `PiaNdcFareFamilyPolicy.php` (`App\Support\Bookings`) | PIA NDC fare-family safety: provider-backed branded options only (ECO LIGHT/SMART/FREEDOM when each has own `provider_context`); reconciles checkout meta and validated snapshot to selected brand. | `collectProviderBackedBrandOptions`, `applySelectedBrandToValidatedSnapshot`, `sanitizeSelectedIntentForPiaNdc`, `reconcileBookingMeta` |
| `PiaNdcSelectedFareReadinessService.php` (`App\Support\Bookings`) | R12Q PNR-ready fare gate: structural provider_context validation + optional checkout OfferPrice probe before passenger page; blocks review confirmation without active option PNR. | `evaluateForCheckout`, `evaluateStructuralForOption`, `bookingHasActiveOptionPnr` |
| `PiaNdcOperationLabels.php` (`App\Support\Bookings`) | R12R canonical Hitit SOAP operation display labels from config keys; sanitizes legacy typos. | `displayForConfigKey`, `sanitizeDisplayOperation`, `applyToSummary` |
| `PiaNdcOperationAuditRecorder.php` (`App\Support\Bookings`) | R12R local audit: `supplier_booking_attempts` rows + booking meta sidecars for ticket preview, ticketing, void, release. | `recordTicketPreview`, `recordTicketing`, `recordVoidTicket`, `recordReleaseOptionPnr` |
| `PiaNdcRetrieveService.php` | DoOrderRetrieve sync + CLI **`runOrderRetrieveDiagnostic`**. | `retrieveAndSync`, `runOrderRetrieveDiagnostic` |
| `PiaNdcOrderOperationPreflight.php` | Shared OrderRetrieve preflight + ticket/void guards for CLI ticketing paths. | `orderContext`, `freshRetrieve`, `duplicateTicketGuard`, `realTicketNumbersPresent` |
| `PiaNdcTicketPreviewService.php` | DoTicketPreview; CLI **`previewDryRun`** / **`runPreview`** (dry-run default). | `preview`, `previewDryRun`, `runPreview` |
| `PiaNdcTicketingService.php` | DoOrderChange MCO ticketing; CLI **`issueTicketsDryRun`**; optional `issueTickets` options. | `issueTickets`, `issueTicketsDryRun` |
| `PiaNdcCancelService.php` | Cancel preview/commit + CLI **`runOrderCancelDiagnostic`** (R11F: live preview-only via **`PREVIEW_OPTION_PNR`** for three preview shapes; fault-safe execute; unticketed option PNR). | `preview`, `commit`, `cancelForBooking`, `runOrderCancelDiagnostic`, `clearStaleCancelLocks` |
| `PiaNdcReleaseOptionPnrService.php` | R12F controlled option PNR release — retrieve + preview + commit (CLI/admin); **`RELEASE_PIA_OPTION_PNR`** confirm phrase. | `runReleaseDiagnostic`, `runReleaseForBooking`, `canReleaseBooking`, `clearStaleReleaseLocks` |
| `PiaNdcReleaseExecutionLock.php` | Commit locks for option PNR release; blocks repeat after successful commit. | `acquire`, `markCommitted`, `isCommitted`, `clearStaleLocks` |
| `PiaNdcCancelEvidenceService.php` | CLI **`pia-ndc:cancel-evidence-report`** — sanitized Hitit support package (no supplier calls; supplier-called failures only in support text). | `buildReport` |
| `PiaNdcCancelExecutionLock.php` | TTL preview vs commit execution locks for cancel diagnostics. | `acquire`, `clearStaleLocks`, `isStale` |
| `PiaNdcVoidTicketService.php` | DoVoidTicket (admin); CLI **`voidTicketDryRun`** / **`runVoid`**. | `voidTicket`, `voidTicketDryRun`, `runVoid` |
| `PiaNdcReissueService.php` | Reissue preview/commit (admin). | `preview`, `commit` |
| `Adapters/PiaNdcFlightSupplierAdapter.php` | `FlightSupplierInterface`. | `search`, `validateOffer` |
| `BookingAdapters/PiaNdcSupplierBookingAdapter.php` | `SupplierBookingInterface`. | `createSupplierBooking` |
| `Duffel/DuffelTicketingService.php` | Sync e-tickets from Duffel order retrieve. | `issueTickets` |
| `TicketingAdapters/DuffelSupplierTicketingAdapter.php` | `SupplierTicketingInterface`. | `issueTickets` |
| `TicketingAdapters/PiaNdcSupplierTicketingAdapter.php` | `SupplierTicketingInterface`. | `issueTickets` |

## `app/Services/Suppliers/AirBlue/`

Dual-channel AirBlue (PA): **`crane_ndc`** (Hitit Crane NDC 20.1) and **`zapways_ota`** (Zapways OTA v2.06). Connection field **`credentials.api_channel`**. Booking meta **`airblue_context`**.

| File | Role | Key APIs |
|------|------|----------|
| `AirBlueClient.php` | Routes NDC vs OTA SOAP calls + `air-blue` log. | `call`, `callNdc`, `callOta` |
| `AirBlueConfigResolver.php` | Channel-aware endpoint/credentials. | `resolve`, `resolveNdc`, `resolveOta`, `apiChannel` |
| `AirBlueXmlBuilder.php` / `AirBlueXmlParser.php` / `AirBlueResponseNormalizer.php` | Crane NDC 20.1 stack. | search/book/ticket/cancel normalizers |
| `AirBlueOtaXmlBuilder.php` / `AirBlueOtaXmlParser.php` / `AirBlueOtaResponseNormalizer.php` | Zapways OTA stack. | `AirLowFareSearch`, book/retrieve normalizers |
| `AirBlueFlightSearchService.php` | Channel branch search. | `search` |
| `AirBlueBookingService.php` | NDC `DoOrderCreate` (+ OTA path when channel=ota). | `createSupplierBooking` |
| `AirBlueRetrieveService.php` | Retrieve/sync `airblue_context`. | `retrieveAndSync` |
| `AirBlueAncillaryService.php` | NDC ancillary probe (seat/baggage samples). | `isSupported`, `logUnavailable` |
| `Adapters/AirBlueFlightSupplierAdapter.php` | `FlightSupplierInterface`. | `search`, `validateOffer` |
| `BookingAdapters/AirBlueSupplierBookingAdapter.php` | `SupplierBookingInterface`. | `createSupplierBooking` |
| `TicketingAdapters/AirBlueSupplierTicketingAdapter.php` | `SupplierTicketingInterface`. | `issueTickets` |

---

## Other service folders (one line each)

| Folder | Role |
|--------|------|
| `Communication/` | **`AgencyMessageTemplateSeeder`** (AGENCY-NOTIFICATION-TEMPLATE-SEED-1 auto-seed on new agency + backfill source), **`AuthSecurityEmailNotificationService`** + **`AuthSecurityEmailPayloadFactory`** (AUTH-SECURITY-EMAIL-1 login success/failure alerts; AUTH-AU3-NEW-DEVICE-SUSPICIOUS-LOGIN-1 `auth_new_device_login` via `notifyNewDeviceLogin()`), **`BookingEmailPayloadFactory`** + **`BookingUniversalNotification`** + **`emails/layouts/universal`** / **`emails/booking/universal-notification`** (EMAIL-UNIVERSAL-1 booking customer/admin payload path), **`OtaNotificationService`** (I6 **`OtaOperationalEmailRenderer`** / **`OtaOperationalNotificationMail`** for generic ops and booking ops without `universal_email`), **`BookingCommunicationService`** (booking customer sends/log guards), **`AbandonedFlightSearchEmailSender`** (I8 marketing recovery), manual console via **`ManualBookingCommunicationEmailRenderer`** (I8), auth registration via **`AuthEmailRenderer`** (I8). **`Support/Emails/EmailTemplateRegistry`** (I3; universal booking labels). **`OperationalEmailDefaults`** (K2D-A auth + K2D-B3 business ops default copy + backfill source). **`EmailTemplatePreviewRenderer`** + **`emails/layouts/modern`** (I4 preview; I5 test; generic live sends). **`ota:backfill-auth-email-templates`**, **`ota:backfill-business-email-templates`**. |

---

## Email modernization QA matrix (I1–I8)

| Path | Status | Send mechanism | DB template body live? |
|------|--------|----------------|----------------------|
| Operational notifications (`OtaNotificationEvent`) | Modernized | **`OtaOperationalEmailRenderer`** | Yes — subject/body from **`agency_message_templates`** when saved |
| Customer booking/payment/ticket/itinerary emails (EMAIL-UNIVERSAL-1) | Universalized | **`BookingEmailPayloadFactory`** → **`BookingUniversalNotification`** | Gate/disable only; body from universal Blade payload |
| Settings test email (I5) | Modernized | **`SettingsTestEmailRenderer`** | Optional row |
| Abandoned flight search (I8) | Modernized | **`AbandonedFlightSearchEmailRenderer`** | No |
| Customer registration welcome (I8) | Modernized | **`CustomerWelcomeMail`** / **`AuthEmailRenderer`** | No |
| Admin new customer signup alert (I8) | Modernized | **`AdminNewCustomerSignupMail`** | No |
| Manual booking-console emails (I8) | Editable · Modernized | **`ManualBookingCommunicationEmailRenderer`** | Yes — subject/body editable; wrapped in modern layout |
| Failed comm resend (booking console, I8) | Modernized | Same as manual | Uses stored log subject/body |
| Failed comm resend (delivery log) | Modernized | **`OtaNotificationService::resendCommunicationLog`** (I6) | Uses stored log |
| Email verification | Framework-managed | Laravel **`VerifyEmail`** notification via **`Registered`** listener; relative-signed verify URL | No |
| Password reset | Framework-managed | Laravel **`Password::sendResetLink`** | No |
| Google welcome (I7) | Modernized | **`GoogleCustomerWelcomeMail`** | No |
| **`OtaTestEmailCommand`** (`ota:test-email`) | Legacy raw | **`Mail::raw`** diagnostic CLI | N/A — ops CLI only |

**Intentional exclusions:** Laravel framework auth emails (verify/reset); **`OtaTestEmailCommand`** raw diagnostic; WhatsApp channel; no DB migration.

**Rollback (I8):** Revert **`RegisteredUserController`** welcome/admin sends to **`Mail::raw`**; **`BookingManagementController`** manual/resend to **`Mail::raw`**; restore **`AbandonedFlightSearchMail`** standalone HTML view; registry **`connectionLabelFor`** / manual entries to pre-I8; delete I8 Mailables/renderers if unused.

**Risks:** Manual template HTML in DB is stripped to safe plain text in send (scripts removed); abandoned-search legacy blade **`emails/marketing/abandoned-flight-search`** unused but kept. Full manual email QA deferred until post-deployment verification.

**Phase complete after:** SFTP upload + **`php artisan view:clear`** + **`php artisan cache:clear`** + spot-send welcome/manual/abandoned on staging + registry admin check.
| `Support/` | **`SupportTicketService`** — ticket create/reply/status/assign; E6 events for created/replied/status-changed. |
| `Payments/` | `BookingPaymentService`, `BookingRefundService`, `PaymentTransactionService`, `PaymentGatewaySettingsService`, `Gateways/AbhiPayGateway`, `PaymentGatewayResolver`. **`Support/Payments/PublicAbhiPayCheckoutPresenter`** — confirmation/review AbhiPay CTA state (PIA NDC active-PNR gating, payment status labels, guest token). **`Services/Suppliers/PiaNdc/PiaNdcBookingStatusRefreshService`** — controlled DoOrderRetrieve refresh + local reconciliation (R12L). |
| `Integrations/` | **JP-INT-01** Admin hub facade: `IntegrationRegistry`, `IntegrationHubService`, `IntegrationManagerResolver`, `IntegrationHealthRecorder`, `AbhiPayDiagnosticPaymentService`, managers (`AbhiPay`, `Supplier`, `Draft`). **`SmtpMailConfigResolver`** / **`GoogleOauthConfigResolver`** DB-first runtime overlays. Controller `Admin\IntegrationsController`. Dashboard `/integrations`. |
| `Promos/` | **`PromoCodeService`** — validate/apply/remove/redeem promo on flight booking checkout payables; **`PromoCodeCalculator`** — percent/fixed discount math (supplier fares unchanged). **`Promo/PromoCodeValidationService`** — admin preview validation. |
| `Documents/` | `BookingDocumentService` — PDFs / document lifecycle. |
| `Dashboard/` | `AgencyDashboardService` — `build()`, `operationalCountsForAgency()`, `buildAdminCommandCenter()` (PNR/payment/staff/agent/failures panels). |
| `resources/views/components/dashboard/` | E1 shared dashboard UI: `kpi-stat`, `quick-action`, `empty-state`, `section-header`, `status-badge` (`.ota-kpi-card`, `.ota-bstat` in `layouts/dashboard.blade.php`). |
| `Reports/` | `BookingReportService` — reporting helpers; **`buildPnrManualReviewDigestSummary()`** (P3 read-only digest metrics); **`buildAgencyBookingActivitySummary()`** (A3 read-only agency activity metrics). |
| `TravelData/` | **`AirportImportService`** (OurAirports-style IATA CSV upsert + overrides), **`AirportProximityService`** (nearby departure IATA via haversine + `ota-flights.nearby_departure_airports`), **`AirlineLogoCacheService`** (local `/storage/airline-logos/{IATA}.png` + **`ota:cache-airline-logos`**; **`isIataOnlyDownloadBlocked()`** blocks generic IATA CDN for collision/override codes), **`AirlineBrandingService`** (`getLogoForCode`, **`publicUrlForLocalMaster`** prefers travel-assets masters before CDN). |

---

## `app/Http/Controllers/` — by audience

| Namespace | Typical concerns |
|-----------|------------------|
| `Frontend/` | Home, flight search, booking checkout, airport autocomplete, guest lookup/cancel, support, demo, agent registration. |
| `Auth/` | Login/register, password reset, email verification, **Socialite** (`SocialAuthController`, **`SocialOAuthClientContext`** shared callback client slug), **Google profile onboarding** (`GoogleOnboarding`, `GoogleOnboardingController`, `GoogleCustomerWelcomeMail`). |
| `Customer/`, `Agent/` | **`SavedTravelerController`** (E11) — saved traveler CRUD; policy-scoped by user (and agency for agents). |
| `Customer/`, `Agent/`, `Staff/`, `Admin/` | **`SupportTicketController`** (E7) — scoped ticket list/create/show/reply; staff/admin status + admin assign. |
| `Admin/` | Dashboard, bookings CRUD/refund/payment/cancel, **`AgencyManagementController`** (agency company index/profile), agents (legacy list/preview), supplier connections, **`IntegrationsController`** (JP-INT-01 hub), agency comms + **notification settings**, message templates, delivery logs, markup, branding, **`AdminSettingsHubController`**, **`AgencyPaymentSettingsController`**, **`CmsPageController`** (static CMS pages), **`PromoCodeController`**, **`CustomerManagementController`** (customer CRM list/profile), safety. |
| `Agent/` | Agent dashboard, bookings, payments/cancellation/commission, **wallet/deposits**, **agency details** (`AgentAgencyController` show/edit/update), **staff** (`AgentStaffController`, `AgentPermission`, `AgentStaffPolicy`), **saved travelers**; middleware **`EnsureAgentPermission`**, **`EnsureAgentAdmin`**. |
| `Agents/` | **`AgentWalletService`** (canonical agency wallet resolve/create, deposit submit/approve/reject; **`canonicalWalletForAgency`**, **`getOrCreateCanonicalWalletForAgency`**, **`canonicalWalletSummary`**; **`agencyWalletSummary`** / **`agencyBalanceSummary`** sum all rows). |
| `Finance/Wallets/` | **`WalletAuditService`** — duplicate wallet classification + audit UI (`build`, `csvRows`, `classificationForWallet`). **`DuplicateWalletArchiveService`** — admin/CLI archive of zero-balance cleanup candidates (status → `archived` only). Artisan **`agent-wallets:audit`**, **`agent-wallets:archive-candidates`**; admin wallet audit + archive preview/POST + CSV export. |
| `Admin/AgentDepositController` | Finance review: list/show deposit requests, approve/reject, secure proof download. |
| `Staff/` | Staff dashboard, booking/payment/refund/cancel. |
| `Customer/` | Customer portal dashboard + bookings + cancel + **saved travelers**. |
| Root / shared | `BookingDocumentController`, `BookingTicketingController`, **`ProfileController`** (universal `user_profiles` + role-specific profile shells), `DashboardRedirectController`. |

---

## `app/Models/` — entities agents touch often

`Booking`, `BookingPassenger`, `SavedTraveler` (E11 encrypted `document_number`), **`UserProfile`** (universal account profile for all roles), `PromoCode` (E12 admin + PROMO-1 checkout payables), `PromoRedemption`,
`BookingContact`, `BookingFareBreakdown`,
`BookingHoldSession`, `SupplierBooking`, `SupplierBookingAttempt`,
`SupplierConnection`, `Agency`, `User`, `Airport`, `SocialAccount`, audit/note
models as needed — see `app/Models/` glob.

---

## `app/Data/`, `app/Enums/`, `app/Support/`

- **Data:** DTOs crossing layers (`NormalizedFlightOfferData`, `SupplierBookingResultData`,
  `SabreBookingOperationResult`, fare/segment breakdowns, etc.).
- **Enums:** `SupplierProvider`, `BookingDocumentType`, `OtaNotificationEvent`, **`AgencyRole`**, …
- **Support:** Client deployment — **`App\Models\ClientProfile`** + modules/suppliers/branding (Dev CP DB source of truth MC-2; MC-3 UI **`DevCpClientProfilesController`** / **`dev.cp.clients.*`** — not wired to runtime gates/views; MC-4 **`CurrentClientContext`** + **`ResolvePreviewClient`** + **`client.preview.*`** placeholder routes under `/{clientSlug}/…`; MC-5A lazy default context + **`ClientAssetResolver`** + **`ReservedClientPreviewSlugs`** + **`client.preview.root`**; MC-6A **`ClientBrandingResolver`** + **`ClientThemeResolver`** + **`client_branding()`** / **`client_theme()`** / **`client_assets()`** helpers — preview pages only; MC-8A **`config/client_themes.php`**, **`ClientThemeRegistry`**, **`RuntimeThemeManager`**, CLI **`ota:client-theme-audit`** — registry-validated resolution, Dev CP theme visibility; MC-8B **`RuntimeViewResolver`**, **`client_view()`** / **`client_layout()`**, CLI **`ota:client-view-audit`**, theme view scaffolds — opt-in resolver, production layouts unchanged until MC-8C), **`DevCpClientProfileManagerService`**, **`ClientProfileResolver`**, **`ClientProfileSyncService`**, **`ClientProfileConfigReader`**, Artisan **`ota:sync-current-client-profile`**, **`App\Support\Client\ClientProfile`** (static runtime from `config/ota_client.php` fallback), **`ClientProfileExporter`** (DB-first **`ota:export-client-profile`** + Dev CP export action — local-safe export to `clients/{slug}/` + asset scaffold; no secrets). Docs **`runtime-theme-engine.md`**, **`runtime-client-branding-theme-resolution.md`**, **`runtime-client-asset-resolution.md`**, **`master-preview-routing.md`**, **`devcp-client-manager-ui.md`**, **`devcp-client-profile-management.md`**, **`client-profile-export-sync.md`**. Branding — **`BrandDisplayResolver`**, **`CompanyEmailProfileResolver`** (I2 platform email identity from default agency settings + communication mail from/reply-to; config fallbacks), **`PublicAgencyContactResolver`** (public header/footer/checkout/support contact channels). Presenters (`BookingListPresenter`, `FlightOfferDisplayPresenter`
  — public card/details: **`journey_overview_display`**, **`segments_display`**, **`layovers_display`**, **`fare_summary_display`**, **`fare_family_options_display`** with explicit **`selection_key_authoritative`** (synthetic display defaults false; backend-resolvable supplier options true); checkout/booking: **`formatCriteriaRouteLabel`**, **`mergeStoredSearchCriteria`**, **`enrichOfferSnapshotForBooking`**,
  **`AirlineDisplayNameResolver`** for public flight-card airline labels,
  **`BookingItineraryOverviewPresenter`** for admin/staff booking Overview itinerary + Payments fare-line-item heuristic; **`meta.pnr_itinerary_snapshot`** (synced via **`SabrePnrItinerarySyncService`** / **`sabre:sync-pnr-itinerary`**),
  **`SabreTripOrdersGetBookingItineraryMapper`**, **`SabrePnrItinerarySyncService`**, **`SabrePnrRetrieveProbe`**),
  **`SabreFareVerificationDigest`** (safe Sabre fare path digest / inspect enrichment),
  **`PublicFlightSearchSecurity`** (public results: debug fare gate, UUID `search_id`, internal URL validation, AJAX offer field sanitization, airport suggestion text strip),
  **`AgencyRoleResolver`** + **`AgencyRolePermissionMatrix`** (display/reporting; `agency_users.agency_role` business roles — not enforced; permissions remain in **`users.meta.agent_permissions`**),
  **`PlatformBrandingResolver`** / **`PlatformBranding`** (admin company name, prefixes, email sender; runtime `app.name` / `mail.from.name`),
  **`BookingReferencePresenter`** (portal display reference `{company}-{CU|AG}-{suffix}`; **`Booking::display_reference`** accessor),
  **`BookingPaymentSummaryPresenter`** (portal payment summary + simplified document rows for customer/agent/guest),
  **`MobileViewPreference`** (public mobile/desktop shell; cookie/session override; orthogonal to UI version channels),
  **`UiVersionResolver`** + helpers **`ui_channel()`** / **`ui_version()`** / **`ui_view()`** / **`ui_asset()`** — channel-based UI versioning (v1 canonical paths; v2+ **`ui/{channel}/{version}/...`** overlays with v1 fallback; site **`/v1`**/**`/v2`** prefix preview; admin/staff **`?ui=`** preview; CLI **`ota:ui-version-audit`**),
  **`UiLayerRegistry`** / **`UiLayerResolver`** + helpers **`ui_layer_contexts()`** / **`ui_layer_asset()`** — layered CSS/JS overrides after base assets; manifest **`config/ui-layers.php`**; Dev CP **`/dev/cp/ui-layers`**; assets **`public/css/layers/`**, **`public/js/layers/`**,
  operational status helpers under `Support/Bookings/` (`PaymentOperationalStatus`, **`SabreOperationalPnrReadiness::evaluate()`** / **`wouldAttemptPnr()`** / **`bypassesLegacyDeferManualReview()`** / **`persistCheckoutMeta()`** (BF7-J-OPS structural operational PNR gate; CLI **`sabre:inspect-operational-pnr-readiness`** + BF8-A **`sabre:operational-pnr-smoke-check`**; meta **`operational_auto_pnr_*`** / **`operational_pnr_readiness`**), **`SabreBrandedFarePublicAutoPnrEligibility::evaluate()`** / **`persistCheckoutEvaluation()`** (BF7-H/I branded-fare public Auto-PNR eligibility; checkout persists **`meta.sabre_public_auto_pnr_eligibility`**; CLI **`sabre:inspect-public-auto-pnr-eligibility`** + **`--reevaluate`**), **`SabrePreCheckoutSellabilityDryRun`** (E5H pre-checkout sellability dry-run; passive **`meta.pre_checkout_sellability_dry_run`**; CLI **`sabre:diagnose-verified-auto-pnr-candidate --precheckout`**), **`SabrePreCheckoutSellabilityPresentation`** (E5I passive customer/staff labels + **`meta.pre_checkout_sellability_presentation`**; derive-on-read; confirmation/admin/CLI surfaces), **`SabrePreCheckoutKnownFailureSoftBlock`** (E5J config-gated soft-block for **`exact_failed_evidence`** / **`host_noop_blocked`**; **`precheckout_known_failure_soft_block_enabled`** default false), **`SabreVerifiedAutoPnrCandidateDiscovery::diagnose()`** + CLI **`sabre:diagnose-verified-auto-pnr-candidate`** for E5G verified-lane evidence/candidate discovery (read-only), **`SabreVerifiedAutoPnrReadiness`**, **`TicketingReadinessPresenter`** for E10 admin/staff ticketing tab checklist, **`PnrItinerarySyncSafetyPresenter`** for Phase F2 admin/staff PNR retrieve/cancel safety card, …), Sabre repair/validators under
  `Support/Suppliers/`, **`PlatformModuleRegistry`** / **`PlatformModuleGate`** / **`PlatformModuleEnforcer`** (`visible()` nav, `routeEnabled()`, service hard-stops: payment/wallet 8M; search/checkout 8N; booking/ticketing 8O; provider channels 8P; presets 8Q). Handover: **`docs/platform-modules.md`**. **`platform.module`** middleware on selected routes; Dev CP at **`dev.cp.modules.index`**).

---

## Cloudflare Turnstile (J2)

| Item | Detail |
|------|--------|
| **Env** | `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` in `.env` → `config/services.php` `turnstile.*`. |
| **Cloudflare** | Create Turnstile widget in Cloudflare dashboard → copy site + secret keys → add production domain. |
| **Verify** | `TurnstileVerifier` POSTs to `https://challenges.cloudflare.com/turnstile/v0/siteverify` (no secret logging). |
| **UI** | `<x-turnstile />` in protected forms; loads `turnstile/v0/api.js` once per page via `@once`. |
| **Protected** | Login, customer register, password reset request, public support, agent application, guest booking lookup, guest checkout passengers + review confirm. |
| **Not protected** | Admin/agent/staff dashboards, AJAX validate-field endpoints, airport autocomplete, flight search GET, token-gated guest actions. |
| **Rollback** | Set `TURNSTILE_ENABLED=false` + `php artisan config:clear` (no code revert required). |
| **Tests** | `tests/Feature/TurnstileProtectionTest.php`. |

---

## Airport catalog (J1)

| Item | Detail |
|------|--------|
| **Source** | Manually uploaded **IATA-code airport dataset** — OurAirports-style CSV at `storage/app/imports/airports.csv` (open community data; **not** an official IATA publication). |
| **Import** | `php artisan airports:import` — options: `--source=`, `--dry-run`, `--truncate`. Legacy Kaggle path: `ota:import-airports-airlines` (airlines + routes). Post-import normalize: `ota:normalize-airports-search`. |
| **Overrides** | `config/airports_overrides.php` + optional `storage/app/imports/airport_overrides.csv` (name, city, country, `is_active`, `priority_score`, `aliases`). |
| **Search API** | `GET /airports/search?q=` → JSON (`iata`, `iata_code`, `name`, `city`, `country`, `label`, `description`; optional `priority_score`, `airport_type`). Ranking: exact IATA → ICAO → city → name → country → keywords; then `priority_score`. |
| **Future IATA** | Swap CSV for licensed official feed; keep command + overrides; set `airports_import.dataset_label` in config. |
| **Rollback** | Re-upload previous `airports.csv` + `php artisan airports:import` (or DB backup restore). `php artisan cache:clear` after import. |
| **Tests** | `tests/Feature/AirportImportTest.php`, `Phase23DFastAutocompleteTest`, `Phase21GTravelDataImportTest`. |

---

## `app/Console/Commands/`

Sabre/Duffel diagnostics (`Sabre*`, `Ota*` prep/import), scheduled-style report
commands (`OtaSend*Report`), **`ota:route-safety-audit --client=haseeb-master`** (MC-5C READ-ONLY default deployment route registry/collision audit — no supplier/DB writes), **`ota:export-client-profile`** (export live client deployment metadata to `clients/{slug}/` + `public/client-assets/{slug}/`; `--from-db`, `--include-assets`, `--force`; no secrets), **`ota:production-readiness-audit`** (F7 READ-ONLY production ops audit — env/cache/queue/mail/storage/logs/scheduler/backup; Sabre mutation flags yes/no; safe on production SSH), **`ota:smoke-live-routes`** (F6/F8 READ-ONLY internal HTTP smoke — Dev CP/admin/public/booking-flow curated routes; F8 adds validation-only POST pass + **`BookingFlowSmokeSafetyOutput`** banner; `--guest-only` production-safe), **`agent-wallets:audit`** (read-only canonical/duplicate wallet report), **`agency-roles:backfill`** (populate `agency_users.agency_role`; `--dry-run` default preview), **`ota:test-email`**, **`ota:seed-access-demo-users`**
(safe demo portal user upsert by email), **`ota:cleanup-access-demo-users`**
(rename legacy demo usernames + promote platform owner), **`airports:import`** (J1 IATA CSV upsert), **`ota:import-airports-airlines`** (legacy Kaggle airlines/routes), **`ota:normalize-airports-search`**. **`sabre:verify-fares`** — local table of raw→normalized→priced totals; **`sabre:inspect-raw-itineraries`** — enriches accepted rows with pricing + `fare_verification_status`; **`sabre:diagnose-booking-segment-sellability`** — B76 per-booking segment OW shop vs snapshot (safe JSON); **`sabre:check-booking-endpoints`** — PNR-related path reachability (empty JSON); **`sabre:check-revalidate-endpoints`** — revalidate/shop path reachability (empty JSON); **`sabre:inspect-pnr-retrieve`** — B84A/B84B.0 PNR retrieve probe (`--shape-tree`, `--map-preview`; production **`--send`** + **`SABRE_PNR_RETRIEVE_INSPECT_ENABLED`**); **`sabre:sync-pnr-itinerary`** — B84B.2 write **`meta.pnr_itinerary_snapshot`** from getBooking (`--dry-run`); **`sabre:inspect-cancel-booking`** — Sprint 0 cancelBooking inspect probe (dry-run default; cert `--send` + **`CANCEL-CERT-PNR`**; production host `--send` + **`CANCEL-LIVE-PROD-PNR`** + **`SABRE_CANCEL_ALLOW_PRODUCTION_HOST`** / **`SABRE_CANCEL_ALLOW_PRODUCTION_SEND`**); **`sabre:cert-token-probe`** — CERT/STL OAuth token probe for env-only manager profiles (`--profile=cert_6md8` / `cert_lu6k` / `cert_test3`; {@see SabreCertTokenProbe}). Grep `class ` in that folder when adding a new command.

---

## Finance follow-ups (planned, not implemented)

**Zero-balance duplicate wallet archive (Finance-Reports-16):** Audit via **`agent-wallets:audit`** / **`/admin/finance/wallet-audit`**. Archive via **`/admin/finance/wallet-audit/archive-preview`** + POST or **`agent-wallets:archive-candidates --agency=ID --dry-run`** then **`--apply --reason="..."`**. Dry-run by default; no auto-archive on deploy.

---

## Tests & E2E

| Path | Role |
|------|------|
| `tests/` | PHPUnit feature + unit. |
| `test/e2e/` | Playwright (`npm run e2e:*`). |
