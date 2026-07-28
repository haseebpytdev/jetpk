# SABRE-GDS-QR-UNTICKETED-BOOK-AND-RETRIEVE-PRODUCTION-READINESS-12

## Objective
Prepare a dedicated, safety-gated QR unticketed book-and-retrieve lifecycle without live supplier calls in this phase.

## Existing command audited
`sabre:gds-live-scenario-runner --mode=book-and-retrieve` performs search, revalidation, PNR create, retrieve (previously **two** retrieves), optional cancel in sibling mode, and uses `LIVE-SABRE-GDS-SCENARIO-RUNNER` confirm phrase.

## Dedicated command introduced
`sabre:gds-qr-unticketed-book-and-retrieve` — plan default (zero supplier calls); `--send` delegates to scenario runner with `lifecycle_dedicated`, `single_retrieve_only`, denylist `FEZJFP`, and strict revalidation handoff gates.

## Files changed
- `app/Console/Commands/SabreGdsQrUnticketedBookAndRetrieveCommand.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedBookAndRetrieveLifecycle.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `tests/Unit/SabreGdsQrUnticketedBookAndRetrieveProductionReadinessPhaseTest.php` (new)

## Final status
Readiness implementation complete locally; live execution requires operator `--send` with production confirmations.
