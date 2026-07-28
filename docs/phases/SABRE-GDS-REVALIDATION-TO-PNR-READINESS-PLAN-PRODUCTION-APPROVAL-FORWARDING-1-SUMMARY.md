# SABRE-GDS-REVALIDATION-TO-PNR-READINESS-PLAN-PRODUCTION-APPROVAL-FORWARDING-1

## Phase name
SABRE-GDS-REVALIDATION-TO-PNR-READINESS-PLAN-PRODUCTION-APPROVAL-FORWARDING-1

## Objective
Forward explicit production ops approval from `sabre:gds-revalidation-to-pnr-readiness-plan` to the internal `sabre:gds-live-scenario-runner --mode=plan` invocation so production plan discovery can proceed without silent approval injection.

## Root cause
The readiness plan command delegated to the scenario runner without `--production-ops-approval`, causing production to fail inside the runner after printing the safety preamble.

## Files changed
- `app/Console/Commands/SabreGdsRevalidationToPnrReadinessPlanCommand.php`
- `tests/Feature/SabreGdsRevalidationToPnrReadinessPlanProductionApprovalForwardingPhaseTest.php`

## Backend changes
- Added `--production-ops-approval=APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER` option.
- Added `resolveProductionGate()` — production requires exact operator-supplied phrase; no silent injection.
- Added `buildScenarioRunnerDelegateOptions()` — forwards operator value only when non-empty; always `--mode=plan`.
- Preserved plan-only guarantees (no PNR/cancel/ticket/passenger/book modes).

## Tests executed
```bash
php artisan test --filter="SabreGdsRevalidationToPnrReadinessPlanProductionApprovalForwardingPhaseTest|SabreGdsRevalidationToPnrCreationReadinessAuditPhaseTest"
```
**17 passed, 57 assertions**

### New test coverage
| Test | Assertions |
|------|------------|
| production without approval fails before runner | exit 1, readiness-plan error, no HTTP |
| wrong approval fails before runner | exit 1, invalid phrase, no HTTP |
| correct approval forwards and runs plan | exit 0, plan preamble, pnr_attempted=false, shop only |
| delegate options plan-only + forwarded approval | mode=plan, approval forwarded, no passenger/cancel keys |
| non-production no approval required | exit 0 |
| non-production does not forward absent approval | no production-ops-approval key in delegate |

## Corrected production command
```bash
php artisan sabre:gds-revalidation-to-pnr-readiness-plan \
  --connection=1 \
  --departure-date=YYYY-MM-DD \
  --fare-pick=brand \
  --confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER \
  --production-ops-approval=APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER
```

## SFTP upload
```
app/Console/Commands/SabreGdsRevalidationToPnrReadinessPlanCommand.php
```

## Final status
**PASS**
