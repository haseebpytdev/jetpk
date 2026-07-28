# Phase 9 — Open requirements closure (22 IDs)

Source of truth: `tests/Support/OneApi/OneApiAcceptanceRequirementMap.php` and `OneApiAcceptanceRequiredIdRegistry::phase9OpenUntilCoveredIds()`.

| ID | Phase | Description | Implementation | Test evidence | Closure |
|---|---|---|---|---|---|
| COMM-005 | 8 | Queue retry idempotency | `SupplierBookingService` | `OneApiCommunicationIntegrationTest::test_queue_retry_idempotency_does_not_duplicate_supplier_booking_created` | Phase 9 |
| COMM-006 | 8 | Reconciliation retry idempotency | `SupplierBookingService` | `OneApiCommunicationIntegrationTest::test_reconciliation_retry_idempotency_does_not_duplicate_supplier_booking_created` | Phase 9 |
| COMM-008 | 8 | Failed modify no success comm | `OneApiSupplierHoldPaymentOrchestrator` | `OneApiCommunicationIntegrationTest::test_failed_modify_does_not_emit_ticket_issued_communication` | Phase 9 |
| HOLD-002 | 8 | Hold feature flag matrix | `OneApiConfigResolver` / `OneApiBookingService` | `OneApiHoldReadPaymentMatrixTest::test_hold_feature_flag_matrix_blocks_hold_when_disabled` | Phase 9 |
| READ-001 | 8 | Read ownership matrix | `OneApiReservationReadOrchestrator` | `OneApiHoldReadPaymentMatrixTest::test_read_ownership_matrix_denies_cross_agency_actor` | Phase 9 |
| PAY-002 | 8 | Hold payment feature-flag matrix | `OneApiHoldPaymentService` | `OneApiHoldReadPaymentMatrixTest::test_hold_payment_feature_flag_matrix_rejects_when_disabled` | Phase 9 |
| COR-003 | 8 | Stale TID rejected | `OneApiBookingService` / `OneApiFareRevalidationService` | `OneApiWorkflowCorruptionMatrixTest@COR-003` | Phase 9 |
| COR-004 | 8 | Missing cookie context rejected | `OneApiWorkflowContextGuard` | `OneApiWorkflowCorruptionMatrixTest@COR-004` | Phase 9 |
| COR-008 | 8 | Search terminal cannot replace price terminal | `OneApiCheckoutSelectionValidator` | `OneApiWorkflowCorruptionMatrixTest@COR-008` | Phase 9 |
| COR-010 | 8 | Changed segment order rejected | `OneApiWorkflowContextGuard` | `OneApiWorkflowCorruptionMatrixTest@COR-010` | Phase 9 |
| COR-011 | 8 | Changed passenger quantities rejected | `OneApiWorkflowContextGuard` | `OneApiWorkflowCorruptionMatrixTest@COR-011` | Phase 9 |
| COR-014 | 8 | Changed booking binding rejected | `OneApiWorkflowContextGuard` | `OneApiWorkflowCorruptionMatrixTest@COR-014` | Phase 9 |
| AUTH-004 | 8 | Auth requirement AUTH-004 (missing tokenPair) | `OneApiAuthService` | `OneApiAuthenticationMatrixTest::test_missing_token_pair_throws` | Phase 9 |
| AUTH-005 | 8 | Auth requirement AUTH-005 (missing access token) | `OneApiAuthService` | `OneApiAuthenticationMatrixTest::test_missing_access_token_throws` | Phase 9 |
| AUTH-006 | 8 | Auth requirement AUTH-006 (cache by environment) | `OneApiAuthService` | `OneApiAuthenticationMatrixTest::test_cache_isolated_by_environment` | Phase 9 |
| AUTH-007 | 8 | Auth requirement AUTH-007 (cache lock) | `OneApiAuthService` | `OneApiAuthenticationMatrixTest::test_cache_lock_prevents_duplicate_auth_storm` | Phase 9 |
| AUTH-008 | 8 | Auth requirement AUTH-008 (JWT / opaque TTL) | `OneApiAuthService` | `OneApiAuthenticationMatrixTest::test_jwt_expiry_and_opaque_fallback_ttl` | Phase 9 |
| AUTH-009 | 8 | Auth requirement AUTH-009 (search 401 retry) | `OneApiRestClient` | `OneApiAuthenticationMatrixTest::test_search_401_retries_once_then_stops` | Phase 9 |
| AUTH-010 | 8 | Auth requirement AUTH-010 (token redaction / no persistence) | `OneApiAuthService` | `OneApiAuthenticationMatrixTest::test_token_redacted_from_logs_and_not_persisted_on_connection` | Phase 9 |
| ADM-002 | 8 | Admin HTTP ADM-002 (platform admin create form) | `SupplierConnectionController` | `OneApiSupplierConnectionAuthorizationTest::test_authorized_platform_admin_can_view_create_form` | Phase 9 |
| ADM-003 | 8 | Admin HTTP ADM-003 (create/update One API connection) | `SupplierConnectionController` | `OneApiSupplierConnectionAuthorizationTest::test_platform_admin_can_create_and_update_one_api_connection` | Phase 9 |
| ADM-004 | 8 | Admin HTTP ADM-004 (blank SOAP readiness) | `OneApiReadinessService` | `OneApiSupplierConnectionAuthorizationTest::test_blank_soap_url_readiness_shows_soap_blocked` | Phase 9 |

Registry guard: `OneApiPhase9OpenRequirementsIntegrityTest` asserts every ID in `phase9OpenUntilCoveredIds()` is `status=covered`.
