# Phase 12 final commit manifest (review-only)

See unified patch `storage/app/one-api-phase-12-unified-review.patch` (167 paths). Staging scripts:

- `storage/app/one-api-phase-12-stage-new-files.ps1` — dedicated PHP, DTO, public JS, One API blades, router integration test
- `storage/app/one-api-phase-12-stage-shared-files.ps1` — shared + mixed (`git add -p`), includes **bootstrap/providers.php** and **BookingCommunicationService.php**
- `storage/app/one-api-phase-12-interactive-stage.md` — human checklist (from Phase 11; add communication service + unified patch note)

**Excluded from commit/deploy:** `tests/Unit/Booking/BookingProviderRouterTest.php`, Sabre-only commands, CMS/Client pages, UI_test, storage evidence CSVs, vendor docs.

**Mixed file staging:** `bootstrap/app.php` (OneApiException JSON only), `routes/web.php` (checkout routes), `BookingCommunicationService.php` (idempotency hunks only).

**Deployment status:** not deployed. **Rollback:** remove One API paths or restore from pre-deploy backup per Phase 10 ops scripts.
