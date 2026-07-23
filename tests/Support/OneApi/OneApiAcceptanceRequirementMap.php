<?php

namespace Tests\Support\OneApi;

/**
 * Machine-readable acceptance traceability (Phases 5–8). Mandatory flags are immutable per registry.
 */
final class OneApiAcceptanceRequirementMap
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function requirements(): array
    {
        $rows = [];
        foreach (OneApiAcceptanceRequiredIdRegistry::entries() as $entry) {
            $rows[] = self::row($entry['id'], $entry['source_phase'], $entry['mandatory'], self::definition($entry['id']));
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function mandatoryMissing(): array
    {
        $missing = [];
        foreach (self::requirements() as $row) {
            if (! ($row['mandatory'] ?? false)) {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            if ($status === 'missing') {
                $missing[] = $row['id'].': '.$row['description'];
            }
            if ($status === 'vendor-fixture-blocked') {
                continue;
            }
            if ($status === 'genuinely not applicable') {
                continue;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    private static function definition(string $id): array
    {
        return match ($id) {
            'OWN-001' => self::covered('workflow ownership', 'Unauthenticated checkout denied', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiWorkflowOwnershipFeatureTest', 'test_unauthenticated_catalog_request_is_rejected', '5'),
            'OWN-002' => self::covered('workflow ownership', 'Cross-user denied', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiWorkflowOwnershipFeatureTest', 'test_http_cross_user_catalog_denied', '5'),
            'OWN-003' => self::covered('workflow ownership', 'Expired context denied', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiSecurityPhase6Test', 'test_expired_context_denied_on_catalog', '5'),
            'OWN-004' => self::covered('workflow ownership', 'Wrong supplier connection denied', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiWorkflowOwnershipFeatureTest', 'test_wrong_supplier_connection_returns_not_found', '5'),
            'OWN-005' => self::covered('workflow ownership', 'Session fingerprint mismatch denied', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiSecurityPhase6Test', 'test_session_fingerprint_mismatch_denied', '5'),
            'TRN-001' => self::covered('transport isolation', 'Production binds live transport', 'OneApiServiceProvider', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiTransportBindingTest', 'test_production_scope_resolves_live_transport', '5'),
            'TRN-002' => self::covered('transport isolation', 'Fixture scope binds fixture transport', 'OneApiServiceProvider', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiTransportBindingTest', 'test_fixture_scope_resolves_fixture_transport', '5'),
            'TRN-003' => self::covered('transport isolation', 'Fixture path rejected without scope', 'OneApiFixtureTransportScope', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiFixtureTransportSecurityTest', 'test_fixture_path_rejected_when_scope_disabled_outside_phpunit_gate', '5'),
            'COMM-001' => self::covered('communication', 'Paid communication idempotent', 'BookingCommunicationService', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_paid_booking_communication_is_idempotent_via_communication_service', '7'),
            'COMM-002' => self::covered('communication', 'Hold suppresses supplier_booking_created', 'SupplierBookingService', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_on_hold_booking_does_not_send_supplier_booking_created', '7'),
            'COMM-003' => self::covered('communication', 'Hold payment ticket_issued once', 'OneApiSupplierHoldPaymentOrchestrator', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_hold_payment_orchestrator_sends_ticket_issued_once', '7'),
            'COMM-004' => self::notApplicable('communication', 'Hold customer email', 'BookingCommunicationService', '', '', '7', 'No hold-specific BookingCommunicationEvent in platform.'),
            'COMM-005' => self::covered('communication', 'Queue retry idempotency', 'SupplierBookingService', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_queue_retry_idempotency_does_not_duplicate_supplier_booking_created', '9'),
            'COMM-006' => self::covered('communication', 'Reconciliation retry idempotency', 'SupplierBookingService', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_reconciliation_retry_idempotency_does_not_duplicate_supplier_booking_created', '9'),
            'COMM-007' => self::covered('communication', 'Ambiguous book no supplier_booking_created', 'OneApiBookingService', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiBookingAmbiguousTest', 'ambiguous_book_is_not_retried', '8'),
            'COMM-008' => self::covered('communication', 'Failed modify no success comm', 'OneApiSupplierHoldPaymentOrchestrator', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_failed_modify_does_not_emit_ticket_issued_communication', '9'),
            'HOLD-001' => self::covered('hold booking', 'Fixture hold lifecycle', 'OneApiBookingService', 'Tests\\Feature\\Suppliers\\OneApiHoldLifecycleIntegrationTest', 'test_hold_read_modify_fixture_lifecycle', '7'),
            'HOLD-002' => self::covered('hold booking', 'Hold feature flag matrix', 'OneApiConfigResolver', 'Tests\\Feature\\Suppliers\\OneApiHoldReadPaymentMatrixTest', 'test_hold_feature_flag_matrix_blocks_hold_when_disabled', '9'),
            'READ-001' => self::covered('reservation read', 'Read ownership matrix', 'OneApiRetrieveService', 'Tests\\Feature\\Suppliers\\OneApiHoldReadPaymentMatrixTest', 'test_read_ownership_matrix_denies_cross_agency_actor', '9'),
            'PAY-001' => self::covered('hold payment', 'Ticketed re-read before communication', 'OneApiSupplierHoldPaymentOrchestrator', 'Tests\\Feature\\Suppliers\\OneApiCommunicationIntegrationTest', 'test_hold_payment_orchestrator_sends_ticket_issued_once', '7'),
            'PAY-002' => self::covered('hold payment', 'Hold payment feature-flag matrix', 'OneApiHoldPaymentService', 'Tests\\Feature\\Suppliers\\OneApiHoldReadPaymentMatrixTest', 'test_hold_payment_feature_flag_matrix_rejects_when_disabled', '9'),
            'MAT-001' => self::covered('24-case matrix', '24 unique case IDs', 'OneApiMatrixCaseRegistry', 'Tests\\Feature\\Suppliers\\OneApiMatrixTwentyFourCasesTest', 'test_registry_contains_twenty_four_unique_case_ids', '6'),
            'MAT-002' => self::covered('24-case matrix', 'Per-row fixture lifecycle', 'OneApiTestMatrixRunner', 'Tests\\Feature\\Suppliers\\OneApiMatrixTwentyFourCasesTest', 'test_each_workbook_case_passes_fixture_lifecycle', '6'),
            'MAT-003' => self::covered('24-case matrix', 'CLI case filter and dry-run', 'OneApiTestMatrixCommand', 'Tests\\Feature\\Console\\OneApiTestMatrixCommandTest', 'matrix_case_filter_runs_single_row', '8'),
            'COR-001' => self::covered('workflow corruption', 'No final_price_confirmed', 'OneApiBookingService', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiWorkflowPropagationTest', 'test_booking_rejected_without_final_price_confirmed', '6'),
            'COR-002' => self::covered('workflow corruption', 'Stale catalog selection', 'OneApiCheckoutFlowService', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiWorkflowPropagationTest', 'test_stale_catalog_selection_rejected', '6'),
            'COR-027' => self::covered('workflow corruption', 'Booking before final price', 'OneApiBookingService', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-027', '8'),
            'COR-003' => self::covered('workflow corruption', 'Stale TID rejected', 'OneApiFareRevalidationService', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-003', '9'),
            'COR-004' => self::covered('workflow corruption', 'Missing cookie context rejected', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-004', '9'),
            'COR-008' => self::covered('workflow corruption', 'Search terminal cannot replace price terminal', 'OneApiCheckoutFlowService', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-008', '9'),
            'COR-010' => self::covered('workflow corruption', 'Changed segment order rejected', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-010', '9'),
            'COR-011' => self::covered('workflow corruption', 'Changed passenger quantities rejected', 'OneApiOfferTokenSigner', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-011', '9'),
            'COR-014' => self::covered('workflow corruption', 'Changed booking binding rejected', 'OneApiWorkflowContextGuard', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@COR-014', '9'),
            'FLY-001' => self::vendorBlocked('search', 'FlyJinnah connection scenario', 'OneApiResponseNormalizer', '', '', '6', 'No sanitized FlyJinnah connection workbook fixture.'),
            default => str_starts_with($id, 'COR-')
                ? (in_array($id, [
                    'COR-005', 'COR-006', 'COR-007', 'COR-009', 'COR-012', 'COR-013', 'COR-015', 'COR-016',
                    'COR-017', 'COR-018', 'COR-019', 'COR-020', 'COR-021', 'COR-022', 'COR-023', 'COR-024',
                    'COR-025', 'COR-026', 'COR-027',
                ], true)
                    ? self::covered('workflow corruption', 'Corruption scenario '.$id, 'OneApiCheckoutSelectionValidator', 'Tests\\Feature\\Suppliers\\OneApiWorkflowCorruptionMatrixTest', 'test_corruption_case_rejects_without_transport_or_communication@'.$id, '8')
                    : self::missing('workflow corruption', 'Corruption scenario '.$id, 'OneApiCheckoutFlowService', '', '', '8'))
                : (str_starts_with($id, 'AUTH-')
                    ? self::authRow($id)
                    : (str_starts_with($id, 'SRCH-') || str_starts_with($id, 'SIG-')
                        ? self::searchRow($id)
                        : (str_starts_with($id, 'PRICE-')
                            ? self::priceRow($id)
                            : (str_starts_with($id, 'ADM-')
                                ? self::adminRow($id)
                                : self::missing('unknown', $id, '', '', '', '8'))))),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function authRow(string $id): array
    {
        $covered = [
            'AUTH-001' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthAndSearchTest', 'test_auth_caches_token_per_connection'],
            'AUTH-002' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiPhase2ClosureTest', 'test_auth_malformed_response_throws'],
            'AUTH-003' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiPhase2ClosureTest', 'test_auth_cache_key_scoped_by_connection'],
            'AUTH-004' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_missing_token_pair_throws'],
            'AUTH-005' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_missing_access_token_throws'],
            'AUTH-006' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_cache_isolated_by_environment'],
            'AUTH-007' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_cache_lock_prevents_duplicate_auth_storm'],
            'AUTH-008' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_jwt_expiry_and_opaque_fallback_ttl'],
            'AUTH-009' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_search_401_retries_once_then_stops'],
            'AUTH-010' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', 'test_token_redacted_from_logs_and_not_persisted_on_connection'],
        ];

        if (isset($covered[$id])) {
            return self::covered('authentication', 'Auth requirement '.$id, 'OneApiAuthService', $covered[$id][0], $covered[$id][1], '6');
        }

        return self::missing('authentication', 'Auth requirement '.$id, 'OneApiAuthService', 'Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthenticationMatrixTest', '', '8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchRow(string $id): array
    {
        $covered = [
            'SRCH-001' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthAndSearchTest', 'test_return_search_uses_actual_return_date'],
            'SRCH-002' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthAndSearchTest', 'test_search_parser_filters_not_available'],
            'SIG-001' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiAuthAndSearchTest', 'test_offer_token_tamper_rejected'],
            'SIG-002' => ['Tests\\Feature\\Suppliers\\OneApiSecurityPhase6Test', 'test_tampered_signed_offer_fingerprint_denied'],
        ];
        if (isset($covered[$id])) {
            return self::covered('search', 'Search/signature '.$id, 'OneApiFlightSearchService', $covered[$id][0], $covered[$id][1], '6');
        }

        return self::missing('search', 'Search requirement '.$id, 'OneApiFlightSearchService', '', '', '8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function priceRow(string $id): array
    {
        $covered = [
            'PRICE-001' => ['Tests\\Unit\\Services\\Suppliers\\OneApi\\OneApiPricingAndBundleTest', 'test_price_parser_reads_total_and_tid'],
            'PRICE-002' => ['Tests\\Feature\\Suppliers\\OneApiCheckoutFlowFeatureTest', 'test_final_price_endpoint_rejects_client_posted_amount'],
        ];
        if (isset($covered[$id])) {
            return self::covered('pricing', 'Pricing '.$id, 'OneApiPricingService', $covered[$id][0], $covered[$id][1], '6');
        }

        return self::missing('pricing', 'Pricing '.$id, 'OneApiPricingService', '', '', '8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function adminRow(string $id): array
    {
        $covered = [
            'ADM-001' => ['Tests\\Feature\\Admin\\OneApiSupplierConnectionFeatureTest', 'test_credentials_are_encrypted_at_rest'],
            'ADM-002' => ['Tests\\Feature\\Admin\\OneApiSupplierConnectionAuthorizationTest', 'test_authorized_platform_admin_can_view_create_form'],
            'ADM-003' => ['Tests\\Feature\\Admin\\OneApiSupplierConnectionAuthorizationTest', 'test_platform_admin_can_create_and_update_one_api_connection'],
            'ADM-004' => ['Tests\\Feature\\Admin\\OneApiSupplierConnectionAuthorizationTest', 'test_blank_soap_url_readiness_shows_soap_blocked'],
        ];
        if (isset($covered[$id])) {
            return self::covered('SupplierConnection authorization', 'Admin '.$id, 'SupplierConnectionController', $covered[$id][0], $covered[$id][1], '6');
        }

        return self::missing('SupplierConnection authorization', 'Admin HTTP '.$id, 'SupplierConnectionController', 'Tests\\Feature\\Admin\\OneApiSupplierConnectionAuthorizationTest', '', '8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(string $id, string $sourcePhase, bool $mandatory, array $def): array
    {
        return array_merge([
            'id' => $id,
            'source_phase' => $sourcePhase,
            'mandatory' => $mandatory,
            'approval_required' => ($def['status'] ?? '') !== 'covered',
        ], $def);
    }

    /**
     * @return array<string, mixed>
     */
    private static function covered(string $category, string $description, string $implementation, string $testClass, string $testMethod, string $phase): array
    {
        return [
            'category' => $category,
            'description' => $description,
            'implementation' => $implementation,
            'test_class' => $testClass,
            'test_method' => $testMethod,
            'status' => 'covered',
            'reason' => '',
            'evidence' => 'Phase '.$phase,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function missing(string $category, string $description, string $implementation, string $testClass, string $testMethod, string $phase): array
    {
        return [
            'category' => $category,
            'description' => $description,
            'implementation' => $implementation,
            'test_class' => $testClass,
            'test_method' => $testMethod,
            'status' => 'missing',
            'reason' => 'Mandatory test not implemented',
            'evidence' => 'Phase '.$phase,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notApplicable(string $category, string $description, string $implementation, string $testClass, string $testMethod, string $phase, string $reason): array
    {
        return [
            'category' => $category,
            'description' => $description,
            'implementation' => $implementation,
            'test_class' => $testClass,
            'test_method' => $testMethod,
            'status' => 'genuinely not applicable',
            'reason' => $reason,
            'evidence' => 'Phase '.$phase.' policy',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function vendorBlocked(string $category, string $description, string $implementation, string $testClass, string $testMethod, string $phase, string $reason): array
    {
        return [
            'category' => $category,
            'description' => $description,
            'implementation' => $implementation,
            'test_class' => $testClass,
            'test_method' => $testMethod,
            'status' => 'vendor-fixture-blocked',
            'reason' => $reason,
            'evidence' => 'Phase '.$phase,
        ];
    }
}
