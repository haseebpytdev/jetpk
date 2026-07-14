<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\FlightSearch\FareSelectionIntegrityValidator;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyDigest;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\SabrePnrLaneDiagnostics;
use App\Support\Suppliers\SupplierPnrFlagGate;

/**
 * Admin/operator-confirmed GDS PNR strategy fallback lane (explicit command only).
 *
 * Does not require operational_auto_pnr_enabled, public_auto_pnr_enabled, or ticketing_enabled.
 */
final class SabreAdminManualPnrFallbackReadiness
{
    public const REASON_ELIGIBLE = 'eligible_admin_manual_fallback';

    public const REASON_BLOCKED_BY_FLAGS = 'blocked_by_flags';

    public const REASON_BLOCKED_ALREADY_HAS_PNR = 'blocked_already_has_pnr';

    public const REASON_BLOCKED_ALREADY_HAS_SUPPLIER_REFERENCE = 'blocked_already_has_supplier_reference';

    public const REASON_BLOCKED_SUCCESSFUL_SUPPLIER_BOOKING = 'blocked_successful_supplier_booking';

    public const REASON_BLOCKED_FARE_MISMATCH = 'branded_fare_context_mismatch';

    public const REASON_BLOCKED_NO_FAILED_ATTEMPT = 'blocked_no_prior_failed_pnr_attempt';

    public const REASON_BLOCKED_STRATEGY_NOT_ALLOWED = 'admin_fallback_strategy_not_allowed';

    public const REASON_BLOCKED_STRATEGY_CONTEXT_NOT_READY = 'admin_fallback_strategy_context_not_ready';

    public const REASON_BLOCKED_SAME_FAILED_STRATEGY = 'admin_fallback_same_failed_strategy_blocked';

    public const REASON_BLOCKED_NOT_SABRE = 'blocked_not_sabre';

    /** @var array<string, string> */
    private const CONDITION_REASON_MAP = [
        'pnr_create_enabled' => self::REASON_BLOCKED_BY_FLAGS,
        'admin_manual_pnr_enabled' => self::REASON_BLOCKED_BY_FLAGS,
        'provider_is_sabre' => self::REASON_BLOCKED_NOT_SABRE,
        'no_pnr' => self::REASON_BLOCKED_ALREADY_HAS_PNR,
        'no_supplier_reference' => self::REASON_BLOCKED_ALREADY_HAS_SUPPLIER_REFERENCE,
        'no_successful_supplier_booking' => self::REASON_BLOCKED_SUCCESSFUL_SUPPLIER_BOOKING,
        'prior_failed_pnr_attempt_exists' => self::REASON_BLOCKED_NO_FAILED_ATTEMPT,
        'fare_context_consistent' => self::REASON_BLOCKED_FARE_MISMATCH,
        'strategy_admin_fallback_allowed' => self::REASON_BLOCKED_STRATEGY_NOT_ALLOWED,
        'strategy_context_ready' => self::REASON_BLOCKED_STRATEGY_CONTEXT_NOT_READY,
        'strategy_not_same_as_failed' => self::REASON_BLOCKED_SAME_FAILED_STRATEGY,
    ];

    public function __construct(
        protected SabreGdsPnrCreateStrategyRegistry $registry,
        protected SabreGdsPnrCreateStrategyDigest $digestBuilder,
        protected SupplierPnrFlagGate $flagGate,
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     reason_code: string,
     *     blocking_conditions: list<string>,
     *     condition_results: array<string, bool>
     * }
     */
    public function evaluate(Booking $booking, string $strategyCode): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);
        $strategyCode = trim($strategyCode);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $flags = $this->flagGate->sabreFlags();

        $pnrPresent = trim((string) ($booking->pnr ?? '')) !== '';
        $supplierReferencePresent = trim((string) ($booking->supplier_reference ?? '')) !== '';
        $successfulSupplierBookingPresent = $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );

        $snapshot = $this->resolveOfferSnapshot($meta);
        $integrity = (new FareSelectionIntegrityValidator)->validate($meta, $snapshot, $snapshot, []);
        $fareConsistent = ($integrity['consistent'] ?? false) === true;

        $failedAttempt = $this->resolveFailedPnrAttempt($booking);
        $previousFailedStrategy = $this->resolvePreviousFailedStrategy($failedAttempt);

        $candidate = collect($this->digestBuilder->buildCandidateDigests($booking))
            ->firstWhere('strategy_code', $strategyCode);
        $strategyAllowed = is_array($candidate)
            && ($candidate['admin_confirmed_fallback_allowed'] ?? false) === true
            && $this->registry->isSupported($strategyCode);
        $strategyContextReady = is_array($candidate) && ($candidate['context_ready'] ?? false) === true;
        $strategyNotSameAsFailed = $previousFailedStrategy === null
            || $strategyCode !== $previousFailedStrategy;

        $conditionResults = [
            'pnr_create_enabled' => $flags['pnr_create_enabled'],
            'admin_manual_pnr_enabled' => $this->flagGate->sabreAdminManualPnrEnabledForCommand(),
            'provider_is_sabre' => $provider === SupplierProvider::Sabre->value,
            'no_pnr' => ! $pnrPresent,
            'no_supplier_reference' => ! $supplierReferencePresent,
            'no_successful_supplier_booking' => ! $successfulSupplierBookingPresent,
            'prior_failed_pnr_attempt_exists' => $failedAttempt !== null,
            'fare_context_consistent' => $fareConsistent,
            'strategy_admin_fallback_allowed' => $strategyAllowed,
            'strategy_context_ready' => $strategyContextReady,
            'strategy_not_same_as_failed' => $strategyNotSameAsFailed,
        ];

        $blockingConditions = SabrePnrLaneDiagnostics::blockingConditionsFromResults(
            $conditionResults,
            SabrePnrLaneDiagnostics::LANE_ADMIN_MANUAL_PNR,
        );

        $allowed = $blockingConditions === [];
        $reasonCode = $allowed
            ? self::REASON_ELIGIBLE
            : (self::CONDITION_REASON_MAP[$blockingConditions[0]] ?? self::REASON_BLOCKED_BY_FLAGS);

        return [
            'allowed' => $allowed,
            'reason_code' => $reasonCode,
            'blocking_conditions' => $blockingConditions,
            'condition_results' => $conditionResults,
        ];
    }

    protected function resolveFailedPnrAttempt(Booking $booking): ?SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->whereIn('status', ['failed', 'manual_review', 'needs_review'])
            ->orderByDesc('id')
            ->first();
    }

    protected function resolvePreviousFailedStrategy(?SupplierBookingAttempt $attempt): ?string
    {
        if ($attempt === null) {
            return null;
        }
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $strategy = trim((string) ($safe['payload_schema'] ?? $safe['payload_style'] ?? $safe['selected_payload_style'] ?? ''));

        return $strategy !== '' ? $strategy : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolveOfferSnapshot(array $meta): array
    {
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            return $meta['normalized_offer_snapshot'];
        }
        if (is_array($meta['validated_offer_snapshot'] ?? null)) {
            return $meta['validated_offer_snapshot'];
        }
        if (is_array($meta['flight_offer_snapshot'] ?? null)) {
            return $meta['flight_offer_snapshot'];
        }

        return [];
    }
}
