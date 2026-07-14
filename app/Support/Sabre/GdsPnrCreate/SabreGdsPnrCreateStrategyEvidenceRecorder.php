<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Models\SabreGdsPnrCreateStrategyEvidence;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabrePnrCertificationSupport;

/**
 * Persists safe known-good / failure evidence for Sabre GDS PNR create strategy learning (no PII / raw payload).
 */
final class SabreGdsPnrCreateStrategyEvidenceRecorder
{
    private const BASELINE_CONN_ID = 2;

    private const BASELINE_VALIDATING_CARRIER = 'PK';

    private const BASELINE_ROUTE_PATTERN = SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER;

    private const BASELINE_TRIP_TYPE = 'one_way_direct';

    private const BASELINE_SEGMENT_COUNT = 1;

    public function __construct(
        protected SabreGdsPnrCreateStrategyRegistry $registry,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreGdsPnrCreateStrategyResultClassifier $resultClassifier,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public function recordSuccess(Booking $booking, string $strategyCode, array $result): void
    {
        $pattern = $this->resolvePattern($booking);
        if ($pattern === null) {
            return;
        }

        $existing = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', $pattern['supplier_connection_id'])
            ->where('strategy_code', $strategyCode)
            ->where('validating_carrier', $pattern['validating_carrier'])
            ->where('route_pattern', $pattern['route_pattern'])
            ->where('trip_type', $pattern['trip_type'])
            ->where('segment_count', $pattern['segment_count'])
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS)
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'endpoint_path' => $this->registry->endpointPathForStrategy($strategyCode),
                'payload_schema' => (string) ($result['payload_schema'] ?? $strategyCode),
                'success_count' => (int) $existing->success_count + 1,
                'last_success_at' => now(),
                'last_success_booking_id' => $booking->id,
            ])->save();

            return;
        }

        SabreGdsPnrCreateStrategyEvidence::query()->create(array_merge($pattern, [
            'strategy_code' => $strategyCode,
            'endpoint_path' => $this->registry->endpointPathForStrategy($strategyCode),
            'payload_schema' => (string) ($result['payload_schema'] ?? $strategyCode),
            'outcome' => SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS,
            'success_count' => 1,
            'last_success_at' => now(),
            'last_success_booking_id' => $booking->id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function recordFailure(Booking $booking, string $strategyCode, array $result): void
    {
        $pattern = $this->resolvePattern($booking);
        if ($pattern === null) {
            return;
        }

        $classification = $this->resultClassifier->classify($result);
        $hostFamily = $classification['host_error_family']
            ?? (is_array($result['sabre_host_classification'] ?? null)
                ? ($result['sabre_host_classification']['host_error_family'] ?? null)
                : null);

        SabreGdsPnrCreateStrategyEvidence::query()->create(array_merge($pattern, [
            'strategy_code' => $strategyCode,
            'endpoint_path' => $this->registry->endpointPathForStrategy($strategyCode),
            'payload_schema' => (string) ($result['payload_schema'] ?? $strategyCode),
            'outcome' => SabreGdsPnrCreateStrategyEvidence::OUTCOME_FAILURE,
            'failed_booking_id' => $booking->id,
            'host_error_family' => $hostFamily,
            'safe_reason_code' => $classification['safe_reason_code'] !== ''
                ? $classification['safe_reason_code']
                : (string) ($result['reason_code'] ?? $result['error_code'] ?? ''),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvePattern(Booking $booking): ?array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connId < 1) {
            return null;
        }

        $readiness = $this->certificationSupport->buildReadiness($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $routePattern = $this->resolveRoutePattern($tripType, $readiness);

        return [
            'supplier_connection_id' => $connId,
            'provider' => 'sabre',
            'distribution_channel' => 'gds',
            'carrier_chain' => implode('→', $carriers),
            'validating_carrier' => strtoupper(trim((string) ($readiness['validating_carrier'] ?? ''))),
            'route_pattern' => $routePattern,
            'trip_type' => $tripType,
            'segment_count' => (int) ($readiness['segment_count'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function resolveRoutePattern(string $tripType, array $readiness): string
    {
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        if ($tripType === 'one_way_direct' || ($segmentCount === 1 && count($carriers) === 1)) {
            return SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER;
        }
        if ($tripType === 'one_way_connecting' && $segmentCount === 2 && count($carriers) === 1) {
            return SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS;
        }
        if ($tripType === 'round_trip') {
            return SabreCertifiedRouteSelector::CATEGORY_RETURN;
        }
        if ($tripType === 'multi_city') {
            return SabreCertifiedRouteSelector::CATEGORY_MULTI_CITY;
        }

        return SabreCertifiedRouteSelector::CATEGORY_UNKNOWN;
    }

    public function findKnownGood(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
    ): ?SabreGdsPnrCreateStrategyEvidence {
        return $this->findBestKnownGood(
            $supplierConnectionId,
            $validatingCarrier,
            $routePattern,
            $tripType,
            $segmentCount,
            SabreGdsPnrCreateStrategyRegistry::SUPPORTED_STRATEGY_CODES,
        );
    }

    /**
     * @param  list<string>  $eligibleStrategies
     */
    public function findBestKnownGood(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        array $eligibleStrategies,
    ): ?SabreGdsPnrCreateStrategyEvidence {
        $eligibleStrategies = array_values(array_filter(array_map(
            static fn (mixed $code): string => trim((string) $code),
            $eligibleStrategies,
        )));
        if ($eligibleStrategies === []) {
            return null;
        }

        $bestCode = null;
        $bestStrength = PHP_INT_MIN;
        $bestSuccessCount = 0;
        $bestLastSuccessBookingId = null;
        $bestLastSuccessAt = null;

        foreach ($eligibleStrategies as $strategyCode) {
            $strength = $this->evidenceStrengthForStrategy(
                $supplierConnectionId,
                $validatingCarrier,
                $routePattern,
                $tripType,
                $segmentCount,
                $strategyCode,
            );
            if ($strength <= 0) {
                continue;
            }

            $successSlice = $this->successEvidenceSlice(
                $supplierConnectionId,
                $validatingCarrier,
                $routePattern,
                $tripType,
                $segmentCount,
                $strategyCode,
            );
            $successCount = (int) ($successSlice['success_count'] ?? 0);
            $lastSuccessAt = $successSlice['last_success_at'] ?? null;

            if ($strength > $bestStrength
                || ($strength === $bestStrength && $successCount > $bestSuccessCount)
                || ($strength === $bestStrength
                    && $successCount === $bestSuccessCount
                    && $lastSuccessAt !== null
                    && ($bestLastSuccessAt === null || $lastSuccessAt > $bestLastSuccessAt))) {
                $bestCode = $strategyCode;
                $bestStrength = $strength;
                $bestSuccessCount = $successCount;
                $bestLastSuccessBookingId = $successSlice['last_success_booking_id'] ?? null;
                $bestLastSuccessAt = $lastSuccessAt;
            }
        }

        if ($bestCode === null) {
            return null;
        }

        $dbRow = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', $supplierConnectionId)
            ->where('strategy_code', $bestCode)
            ->where('validating_carrier', strtoupper(trim($validatingCarrier)))
            ->where('route_pattern', $routePattern)
            ->where('trip_type', $tripType)
            ->where('segment_count', $segmentCount)
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS)
            ->orderByDesc('success_count')
            ->orderByDesc('last_success_at')
            ->first();

        if ($dbRow !== null) {
            return $dbRow;
        }

        return new SabreGdsPnrCreateStrategyEvidence([
            'supplier_connection_id' => $supplierConnectionId,
            'provider' => 'sabre',
            'distribution_channel' => 'gds',
            'strategy_code' => $bestCode,
            'endpoint_path' => $this->registry->endpointPathForStrategy($bestCode),
            'payload_schema' => $bestCode,
            'carrier_chain' => self::BASELINE_VALIDATING_CARRIER,
            'validating_carrier' => strtoupper(trim($validatingCarrier)),
            'route_pattern' => $routePattern,
            'trip_type' => $tripType,
            'segment_count' => $segmentCount,
            'outcome' => SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS,
            'success_count' => $bestSuccessCount,
            'last_success_booking_id' => $bestLastSuccessBookingId,
            'last_success_at' => $bestLastSuccessAt,
        ]);
    }

    public function hasMixedSuccessFormatFailureEvidence(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        string $strategyCode,
    ): bool {
        $successSlice = $this->successEvidenceSlice(
            $supplierConnectionId,
            $validatingCarrier,
            $routePattern,
            $tripType,
            $segmentCount,
            $strategyCode,
        );

        return (int) ($successSlice['success_count'] ?? 0) > 0
            && $this->formatFailureCountForStrategy(
                $supplierConnectionId,
                $validatingCarrier,
                $routePattern,
                $tripType,
                $segmentCount,
                $strategyCode,
            ) > 0;
    }

    public function evidenceStrengthForStrategy(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        string $strategyCode,
    ): int {
        $successCount = (int) ($this->successEvidenceSlice(
            $supplierConnectionId,
            $validatingCarrier,
            $routePattern,
            $tripType,
            $segmentCount,
            $strategyCode,
        )['success_count'] ?? 0);
        $failureCount = $this->formatFailureCountForStrategy(
            $supplierConnectionId,
            $validatingCarrier,
            $routePattern,
            $tripType,
            $segmentCount,
            $strategyCode,
        );

        return $successCount - $failureCount;
    }

    /**
     * @return array{success_count: int, last_success_booking_id: int|null, last_success_at: \Illuminate\Support\Carbon|null}
     */
    protected function successEvidenceSlice(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        string $strategyCode,
    ): array {
        $validatingCarrier = strtoupper(trim($validatingCarrier));
        $strategyCode = trim($strategyCode);

        $dbRow = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', $supplierConnectionId)
            ->where('strategy_code', $strategyCode)
            ->where('validating_carrier', $validatingCarrier)
            ->where('route_pattern', $routePattern)
            ->where('trip_type', $tripType)
            ->where('segment_count', $segmentCount)
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS)
            ->orderByDesc('success_count')
            ->orderByDesc('last_success_at')
            ->first();

        $baseline = $this->baselineSuccessSlice(
            $supplierConnectionId,
            $validatingCarrier,
            $routePattern,
            $tripType,
            $segmentCount,
            $strategyCode,
        );

        if ($dbRow === null) {
            return $baseline;
        }

        if ($baseline['success_count'] === 0) {
            return [
                'success_count' => (int) $dbRow->success_count,
                'last_success_booking_id' => $dbRow->last_success_booking_id,
                'last_success_at' => $dbRow->last_success_at,
            ];
        }

        return [
            'success_count' => max((int) $dbRow->success_count, $baseline['success_count']),
            'last_success_booking_id' => $dbRow->last_success_booking_id ?? $baseline['last_success_booking_id'],
            'last_success_at' => $dbRow->last_success_at ?? $baseline['last_success_at'],
        ];
    }

    protected function formatFailureCountForStrategy(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        string $strategyCode,
    ): int {
        $dbCount = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', $supplierConnectionId)
            ->where('strategy_code', trim($strategyCode))
            ->where('validating_carrier', strtoupper(trim($validatingCarrier)))
            ->where('route_pattern', $routePattern)
            ->where('trip_type', $tripType)
            ->where('segment_count', $segmentCount)
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_FAILURE)
            ->where('host_error_family', SabreHostErrorClassifier::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT)
            ->count();

        return $dbCount + $this->baselineFormatFailureCount(
            $supplierConnectionId,
            $validatingCarrier,
            $routePattern,
            $tripType,
            $segmentCount,
            $strategyCode,
        );
    }

    /**
     * @return array{success_count: int, last_success_booking_id: int|null, last_success_at: \Illuminate\Support\Carbon|null}
     */
    protected function baselineSuccessSlice(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        string $strategyCode,
    ): array {
        if (! $this->baselineContextMatches($supplierConnectionId, $validatingCarrier, $routePattern, $tripType, $segmentCount)) {
            return ['success_count' => 0, 'last_success_booking_id' => null, 'last_success_at' => null];
        }

        return match (trim($strategyCode)) {
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS => [
                'success_count' => 2,
                'last_success_booking_id' => 95,
                'last_success_at' => now(),
            ],
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1 => [
                'success_count' => 1,
                'last_success_booking_id' => 94,
                'last_success_at' => now(),
            ],
            default => ['success_count' => 0, 'last_success_booking_id' => null, 'last_success_at' => null],
        };
    }

    protected function baselineFormatFailureCount(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
        string $strategyCode,
    ): int {
        if (! $this->baselineContextMatches($supplierConnectionId, $validatingCarrier, $routePattern, $tripType, $segmentCount)) {
            return 0;
        }

        return trim($strategyCode) === SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1 ? 1 : 0;
    }

    protected function baselineContextMatches(
        int $supplierConnectionId,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
    ): bool {
        return $supplierConnectionId === self::BASELINE_CONN_ID
            && strtoupper(trim($validatingCarrier)) === self::BASELINE_VALIDATING_CARRIER
            && $routePattern === self::BASELINE_ROUTE_PATTERN
            && $tripType === self::BASELINE_TRIP_TYPE
            && $segmentCount === self::BASELINE_SEGMENT_COUNT;
    }
}
