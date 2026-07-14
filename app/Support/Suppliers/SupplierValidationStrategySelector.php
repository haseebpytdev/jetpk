<?php

namespace App\Support\Suppliers;

use App\Models\Booking;
use App\Models\SupplierValidationStrategyEvidence;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;

/**
 * Select exactly one validation/freshness strategy per booking context (no multi-strategy live retry).
 */
final class SupplierValidationStrategySelector
{
    public const REASON_OFFER_REFRESH_SATISFIED = 'offer_refresh_satisfied';

    public const REASON_BFM_REVALIDATION_REQUIRED = 'bfm_revalidation_required';

    public const REASON_PRE_PNR_SKIP = 'pre_pnr_freshness_not_required';

    public const REASON_NDC_OFFER_PRICE = 'ndc_offer_price_required';

    public const REASON_BLOCKED_AUTOMATIC = 'automatic_not_allowed';

    public function __construct(
        protected SupplierValidationStrategyRegistry $registry,
        protected SupplierValidationStrategyEvidenceRecorder $evidenceRecorder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function selectForBooking(Booking $booking, string $action): array
    {
        $action = strtolower(trim($action));
        if (! SupplierValidationActionCode::isSupported($action)) {
            return [
                'selected_strategy' => null,
                'selection_reason' => 'unsupported_action',
                'eligible_strategies' => [],
                'blocked_strategies' => [],
                'fallback_available' => false,
            ];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $candidates = [];
        foreach ($this->registry->supportedCodesForAction($action) as $code) {
            $candidates[] = $this->registry->get($code);
        }

        $eligible = [];
        $blocked = [];
        foreach ($candidates as $definition) {
            $code = (string) ($definition['strategy_code'] ?? '');
            $blockers = $this->blockersForDefinition($booking, $definition);
            if ($blockers === []) {
                $eligible[] = $code;
            } else {
                $blocked[] = ['strategy_code' => $code, 'blockers' => $blockers];
            }
        }

        $selected = null;
        $reason = 'no_eligible_strategy';
        $fallback = false;

        if ($action === SupplierValidationActionCode::GDS_PRE_PNR_FRESHNESS) {
            $selection = $this->selectGdsPrePnrFreshness($booking, $eligible, $meta);
            $selected = $selection['selected_strategy'];
            $reason = $selection['selection_reason'];
            $fallback = $selection['fallback_available'];
        } elseif ($action === SupplierValidationActionCode::GDS_OFFER_REFRESH) {
            $selected = in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_OFFER_REFRESH, $eligible, true)
                ? SupplierValidationStrategyRegistry::STRATEGY_GDS_OFFER_REFRESH
                : null;
            $reason = $selected !== null ? self::REASON_OFFER_REFRESH_SATISFIED : 'offer_refresh_not_eligible';
        } elseif ($action === SupplierValidationActionCode::GDS_FARE_REVALIDATION) {
            $selected = in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION, $eligible, true)
                ? SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION
                : null;
            $reason = $selected !== null ? self::REASON_BFM_REVALIDATION_REQUIRED : 'revalidation_not_eligible';
            $fallback = in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION, $eligible, true)
                && ($this->registry->get(SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION)['admin_confirmed_fallback_allowed'] ?? false) === true;
        } elseif ($action === SupplierValidationActionCode::NDC_OFFER_PRICE) {
            $selected = in_array(SupplierValidationStrategyRegistry::STRATEGY_NDC_OFFER_PRICE, $eligible, true)
                ? SupplierValidationStrategyRegistry::STRATEGY_NDC_OFFER_PRICE
                : null;
            $reason = $selected !== null ? self::REASON_NDC_OFFER_PRICE : 'ndc_offer_price_not_eligible';
        }

        if ($selected !== null && $connId > 0) {
            $knownGood = $this->evidenceRecorder->findLatestSuccess(
                $connId,
                $action,
                $selected,
            );
            if ($knownGood !== null) {
                $reason .= '_known_good';
            }
        }

        return [
            'selected_strategy' => $selected,
            'selection_reason' => $reason,
            'eligible_strategies' => $eligible,
            'blocked_strategies' => $blocked,
            'fallback_available' => $fallback,
            'automatic_multi_strategy_retry' => false,
            'action' => $action,
        ];
    }

    /**
     * @param  list<string>  $eligible
     * @param  array<string, mixed>  $meta
     * @return array{selected_strategy: ?string, selection_reason: string, fallback_available: bool}
     */
    protected function selectGdsPrePnrFreshness(Booking $booking, array $eligible, array $meta): array
    {
        $refreshSlice = is_array($meta['offer_refresh'] ?? null) ? $meta['offer_refresh'] : [];
        $style = (string) ($meta['selected_pnr_strategy_code'] ?? $meta['selected_payload_style'] ?? '');
        $isGdsV25 = $style === SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS
            || $style === SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS;
        $refreshOk = $this->refreshSliceLooksSatisfied($refreshSlice);

        if ($isGdsV25 && $refreshOk
            && in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_OFFER_REFRESH, $eligible, true)) {
            return [
                'selected_strategy' => SupplierValidationStrategyRegistry::STRATEGY_GDS_OFFER_REFRESH,
                'selection_reason' => self::REASON_OFFER_REFRESH_SATISFIED,
                'fallback_available' => in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION, $eligible, true),
            ];
        }

        $revalidationRequired = (bool) config('suppliers.sabre.revalidation_before_booking', false);
        if ($revalidationRequired
            && in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION, $eligible, true)) {
            return [
                'selected_strategy' => SupplierValidationStrategyRegistry::STRATEGY_GDS_BFM_REVALIDATION,
                'selection_reason' => self::REASON_BFM_REVALIDATION_REQUIRED,
                'fallback_available' => true,
            ];
        }

        if (in_array(SupplierValidationStrategyRegistry::STRATEGY_GDS_PRE_PNR_SKIP, $eligible, true)) {
            return [
                'selected_strategy' => SupplierValidationStrategyRegistry::STRATEGY_GDS_PRE_PNR_SKIP,
                'selection_reason' => self::REASON_PRE_PNR_SKIP,
                'fallback_available' => false,
            ];
        }

        return [
            'selected_strategy' => $eligible[0] ?? null,
            'selection_reason' => $eligible !== [] ? 'first_eligible' : 'no_eligible_strategy',
            'fallback_available' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    protected function blockersForDefinition(Booking $booking, array $definition): array
    {
        $blockers = [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        foreach ($definition['required_context_fields'] ?? [] as $field) {
            $field = (string) $field;
            if ($field === 'supplier_connection_id' && (int) ($meta['supplier_connection_id'] ?? 0) <= 0) {
                $blockers[] = 'missing_supplier_connection_id';
            }
            if ($field === 'sabre_booking_context' && ! is_array($meta['sabre_booking_context'] ?? null)) {
                $blockers[] = 'missing_sabre_booking_context';
            }
            if ($field === 'selected_offer_id' && trim((string) ($meta['selected_offer_id'] ?? '')) === '') {
                $blockers[] = 'missing_selected_offer_id';
            }
        }
        if (($definition['automatic_allowed'] ?? false) !== true) {
            $blockers[] = 'automatic_not_allowed';
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @param  array<string, mixed>  $refreshSlice
     */
    protected function refreshSliceLooksSatisfied(array $refreshSlice): bool
    {
        if (($refreshSlice['requires_customer_confirmation'] ?? false) === true
            && ($refreshSlice['accepted'] ?? false) !== true) {
            return false;
        }
        $refreshResult = strtolower(trim((string) ($refreshSlice['refresh_result'] ?? '')));
        $refreshStatus = strtolower(trim((string) ($refreshSlice['refresh_status'] ?? '')));

        return $refreshResult === 'ok'
            || in_array($refreshStatus, ['refreshed', 'success'], true)
            || ($refreshSlice['refresh_or_revalidation_satisfied'] ?? false) === true;
    }
}
