# SABRE-GDS-REVALIDATION-TO-PNR-CREATION-READINESS-AUDIT-1

## Phase name
SABRE-GDS-REVALIDATION-TO-PNR-CREATION-READINESS-AUDIT-1

## Branch name
`claude/sabre-gds-revalidation-to-pnr-creation-readiness-audit-1` (create from `claude/ui-master` before commit)

## Objective
Audit and harden the Sabre GDS path from booking entry through `bfm_revalidate_v1` to unticketed PNR creation readiness — no live supplier calls in this phase.

## Call graph (booking entry → revalidation → PNR)

```
BookingController::review (POST)
  → SabreBookingService::runPublicReviewDryRun
    → SabreBookingService::createBooking
      → decideSabreBookingFreshnessStrategy
      → runRevalidationBeforeBooking (when revalidation_required)
        → SabreRevalidationPayloadBuilder::buildPayload / assertGatekeeperOrThrow
        → SabreClient::postRevalidatePayload
        → SabreGdsRevalidationApplicationMessageDiagnostics::analyze / hasBlockingMessages
        → SabreGdsRevalidationResponseCandidateLinker::analyze (unique match required)
        → extractLinkageForSelectedCandidate (no index-0 fallback)
        → evaluateRevalidationPricingTripwire (fare-change fail-closed)
        → SabreGdsRevalidationSanitizedOutcomeContract::wrap
      → buildLiveBookingEnvelope → SabreBookingPayloadBuilder
      → SabreBookingClient POST (Trip Orders / Passenger Records)
  → finalizePublicCheckoutSabreStorage / persistLiveSabrePnrOnBooking
    → SabreGdsAutoPnrLifecycleService::maybeAutoSyncPnrItineraryAfterPnrCreate (retrieve)

Scenario runner (controlled lifecycle):
sabre:gds-live-scenario-runner / sabre:gds-revalidation-to-pnr-readiness-plan
  → SabreGdsLiveScenarioRevalidationGate → SabreGdsRevalidationService::revalidateDraft
  → SabreGdsLiveScenarioRunnerPnrExecutor → createBookingForScenarioRunner
```

## Investigation findings

### Invariant enforcement (after this phase)

| Invariant | Status |
|---|---|
| Unique linkage required before PNR | **Hardened** — removed `extractFareLinkage` fallback on live path |
| Informational warnings non-blocking | Enforced via `hasBlockingMessages` |
| Blocking warnings/errors fail pre-PNR | Enforced |
| Fare change fail-closed (BFM tripwire) | `evaluateRevalidationPricingTripwire` |
| Public fare-change acceptance | `SabreOfferRefreshAcceptance` + controller modal (separate from BFM tripwire) |
| One revalidation + one PNR call | Enforced by createBooking flow; no auto-retry on success |
| Ticketing disabled | `ticketing_enabled=false`; `issueTicket` returns disabled |
| Cancel requires confirmation | `SabreBookingCancelService::workflowLiveCancelGates` |

### Remaining bypass paths (documented, not removed)

- `allow_createbooking_without_revalidation` (non-production only)
- IATI / offer-refresh freshness waive of BFM revalidation
- `pnr_only_waive_mandatory_revalidation` config
- Scenario/staff certification overrides

### Fare-change acceptance policy

| Layer | Behavior |
|---|---|
| BFM revalidate (createBooking) | Hard fail via pricing tripwire (~1% tolerance); no silent accept |
| Offer refresh (public checkout) | Requires explicit customer acceptance before PNR |
| Scenario runner | `scenario_fare_change_requires_acceptance` via outcome mapper |

## Root causes corrected

1. **Non-unique linkage fallback** — `runRevalidationBeforeBooking` could succeed via `extractFareLinkage()` (first/current itinerary) when linker reported ambiguous/zero unique match.
2. **Contract OR-promotion** — `SabreGdsRevalidationSanitizedOutcomeContract` could set `usable_fare_linkage=true` from digest when linker diagnostics said false.

## Exact files changed

### App
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Console/Commands/SabreGdsRevalidationToPnrReadinessPlanCommand.php` *(new)*

### Tests / docs
- `tests/Unit/SabreGdsRevalidationToPnrCreationReadinessAuditPhaseTest.php` *(new)*
- `docs/phases/SABRE-GDS-REVALIDATION-TO-PNR-CREATION-READINESS-AUDIT-1-SUMMARY.md` *(this file)*

## Tests executed

```bash
php artisan test --filter=SabreGdsRevalidationToPnrCreationReadinessAuditPhaseTest
php artisan test --filter=SabreRevalidation
```

- Audit: **11 passed**
- Revalidation regression: **121 passed**

## Production plan command (no PNR, no cancel)

```bash
php artisan sabre:gds-revalidation-to-pnr-readiness-plan \
  --connection=1 \
  --departure-date=YYYY-MM-DD \
  --fare-pick=brand \
  --confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER
```

Delegates to `sabre:gds-live-scenario-runner --mode=plan` with `LHE`→`JED`, `--preset=qr-connecting`, `--carrier=QR`, `--stops=1`.

## Controlled unticketed PNR lifecycle (after plan passes — separate authorization)

```bash
php artisan sabre:gds-live-scenario-runner \
  --connection=1 \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --preset=qr-connecting \
  --carrier=QR \
  --stops=1 \
  --fare-pick=brand \
  --mode=book-and-retrieve \
  --passenger-json=/path/to/private/passenger.json \
  --max-bookings=1 \
  --confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER
```

Stop before `--mode=book-retrieve-and-cancel` (requires `--cancel-approval`).

## Readiness verdict

**Conditionally ready** for one controlled unticketed PNR lifecycle after:
1. Deploying this phase (unique linkage hardening)
2. Successful plan run with branded QR fare + `revalidation_linkage_ready=true`
3. Operator approval with `--confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER`

## Final status
**PASS** — audit complete, linkage hardening applied, 132 revalidation+audit tests green; no live supplier call in this phase.
