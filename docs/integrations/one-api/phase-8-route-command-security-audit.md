# Phase 8 — Route and command security (post hold-payment orchestration)

## HTTP (One API checkout)

| Control | Status |
|---------|--------|
| Auth middleware on catalog/final-price/selections | Unchanged — `routes/web.php` |
| CSRF on POST mutations | Web stack |
| No fixture_path on final-price body | `OneApiWorkflowOwnershipFeatureTest` |
| Workflow ownership guard | Phase 5–8 tests |

Hold-payment orchestration (`SupplierBookingService::payHeldOneApiReservation`) is **not** exposed as a public HTTP route; no new GET mutations added.

## CLI

Matrix/fixture/probe commands unchanged; live gates on matrix `mode=live`.

## Secret handling

Auth logs use `SensitiveDataRedactor`; tokens not stored on `SupplierConnection` or workflow context.

See `storage/app/one-api-phase-8-secret-scan.txt`.
