<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;

/**
 * Final post-revalidation offer validation for QR unticketed lifecycle (authoritative context only).
 */
final class SabreGdsQrUnticketedPostRevalidationFinalOfferValidator
{
    public function __construct(
        private readonly SabreBookingService $sabreBookingService,
    ) {}

    /**
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @return array<string, mixed>
     */
    public function validate(
        SabreGdsAuthoritativeRevalidatedBookingContext $context,
        array $revalidationEvidence,
        array $passengerBundle,
    ): array {
        $attempted = true;
        $diagnostics = $context->safeDiagnostics;

        if (! $this->revalidationHandoffGatesSatisfied($revalidationEvidence)) {
            return $this->failure(
                'revalidation_handoff_not_ready',
                'revalidation_handoff_allows_pnr_create',
                ['handoff_block_reasons' => $this->revalidationHandoffBlockReasons($revalidationEvidence)],
                $attempted,
                $diagnostics,
            );
        }

        if (($diagnostics['shopping_to_revalidation_identifier_transition'] ?? '') === SabreGdsAuthoritativeRevalidatedBookingContext::TRANSITION_REJECTED) {
            return $this->failure(
                SabreGdsAuthoritativeRevalidatedBookingContext::TRANSITION_REJECTED,
                'identifier_transition_compatibility',
                [
                    'selected_offer_fingerprint' => $diagnostics['selected_offer_fingerprint'] ?? null,
                    'revalidated_offer_fingerprint' => $diagnostics['revalidated_offer_fingerprint'] ?? null,
                    'selected_draft_signature_equal' => $diagnostics['selected_draft_signature_equal'] ?? null,
                ],
                $attempted,
                $diagnostics,
            );
        }

        if (($diagnostics['passenger_context_valid'] ?? false) !== true) {
            return $this->failure(
                'passenger_context_invalid',
                'passenger_bundle_required_fields',
                [],
                $attempted,
                $diagnostics,
            );
        }

        $snap = $context->normalizedOfferSnapshot;
        $provider = strtolower(trim((string) ($snap['supplier_provider'] ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return $this->failure(
                'wrong_provider',
                'supplier_provider===sabre',
                ['supplier_provider' => $provider],
                $attempted,
                $diagnostics,
            );
        }

        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        $bookableSegmentCount = (int) ($diagnostics['segment_count'] ?? count($segments));
        if ($bookableSegmentCount < 1 || count($segments) !== $bookableSegmentCount) {
            return $this->failure(
                'segment_count_mismatch',
                'bookable_segment_count_matches_snapshot',
                [
                    'bookable_segment_count' => $bookableSegmentCount,
                    'snapshot_segment_count' => count($segments),
                ],
                $attempted,
                $diagnostics,
            );
        }

        foreach (['booking_classes_by_segment', 'fare_basis_codes_by_segment'] as $listKey) {
            $list = is_array($diagnostics[$listKey] ?? null) ? $diagnostics[$listKey] : [];
            if (! $this->perSegmentListComplete($list, count($segments))) {
                return $this->failure(
                    $listKey === 'booking_classes_by_segment' ? 'booking_class_missing' : 'fare_basis_missing',
                    $listKey.'_complete_for_all_segments',
                    ['segment_count' => count($segments), 'list_count' => count($list)],
                    $attempted,
                    $diagnostics,
                );
            }
        }

        $amount = (float) ($diagnostics['pricing_amount'] ?? 0);
        $currency = trim((string) ($diagnostics['pricing_currency'] ?? ''));
        if ($amount <= 0) {
            return $this->failure('missing_fare_amount', 'pricing_amount>0', ['pricing_amount' => $amount], $attempted, $diagnostics);
        }
        if ($currency === '') {
            return $this->failure('missing_currency', 'pricing_currency_present', [], $attempted, $diagnostics);
        }

        $gate = $this->sabreBookingService->validateNormalizedSabreOffer($snap);
        if (! $gate->success) {
            return $this->failure(
                (string) ($gate->safe_context['reason'] ?? 'offer_structural_validation_failed'),
                'validateNormalizedSabreOffer',
                [
                    'safe_context_reason' => $gate->safe_context['reason'] ?? null,
                    'source' => 'authoritative_revalidated_snapshot',
                ],
                $attempted,
                $diagnostics,
            );
        }

        return [
            'final_offer_validation_attempted' => true,
            'final_offer_validation_success' => true,
            'final_offer_validation_reason_code' => 'authoritative_post_revalidation_offer_valid',
            'final_offer_validation_predicate' => 'authoritative_context_gates_and_structural_validation',
            'final_offer_validation_inputs' => [
                'segment_count' => count($segments),
                'pricing_amount' => $amount,
                'pricing_currency' => $currency,
                'unique_usable_linkage_match_count' => $diagnostics['unique_usable_linkage_match_count'] ?? null,
            ],
            'pre_create_gate_complete' => true,
            'authoritative_context_built' => true,
            'shopping_to_revalidation_identifier_transition' => $diagnostics['shopping_to_revalidation_identifier_transition'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $values
     */
    protected function perSegmentListComplete(array $values, int $segmentCount): bool
    {
        if ($segmentCount < 1 || count($values) < $segmentCount) {
            return false;
        }
        for ($i = 0; $i < $segmentCount; $i++) {
            if (trim((string) ($values[$i] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    protected function failure(
        string $reasonCode,
        string $predicate,
        array $inputs,
        bool $attempted,
        array $diagnostics,
    ): array {
        return [
            'final_offer_validation_attempted' => $attempted,
            'final_offer_validation_success' => false,
            'final_offer_validation_reason_code' => $reasonCode,
            'final_offer_validation_predicate' => $predicate,
            'final_offer_validation_inputs' => $inputs,
            'pre_create_gate_complete' => false,
            'authoritative_context_built' => ($diagnostics['authoritative_context_built'] ?? false) === true,
            'shopping_to_revalidation_identifier_transition' => $diagnostics['shopping_to_revalidation_identifier_transition'] ?? null,
            'safe_reason_code' => $reasonCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    protected function revalidationHandoffGatesSatisfied(array $evidence): bool
    {
        if (($evidence['revalidation_success'] ?? false) !== true) {
            return false;
        }
        if (($evidence['freshness_satisfied'] ?? false) !== true) {
            return false;
        }
        $diagnostics = is_array($evidence['revalidation_diagnostics'] ?? null) ? $evidence['revalidation_diagnostics'] : [];
        $unique = (int) ($diagnostics['unique_usable_linkage_match_count'] ?? $evidence['unique_usable_linkage_match_count'] ?? 0);
        $ambiguous = (int) ($diagnostics['ambiguous_linkage_match_count'] ?? $evidence['ambiguous_linkage_match_count'] ?? -1);
        if ($unique !== 1 || $ambiguous !== 0) {
            return false;
        }
        foreach (['pricing_complete', 'fare_basis_complete', 'usable_fare_linkage'] as $key) {
            if (($diagnostics[$key] ?? $evidence[$key] ?? null) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    protected function revalidationHandoffBlockReasons(array $evidence): array
    {
        return app(SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff::class)->blockReasons($evidence);
    }
}
