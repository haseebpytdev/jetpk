<?php

namespace App\Support\Suppliers;

use App\Models\SabreGdsPnrCreateStrategyEvidence;

/**
 * Safe known-good strategy evidence lookup (no PII / raw payload).
 */
final class SupplierKnownGoodStrategyStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function bestSuccessEvidence(
        int $supplierConnectionId,
        string $provider,
        string $strategyCode,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
    ): ?array {
        $row = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', $supplierConnectionId)
            ->where('provider', $provider)
            ->where('strategy_code', $strategyCode)
            ->where('validating_carrier', $validatingCarrier)
            ->where('route_pattern', $routePattern)
            ->where('trip_type', $tripType)
            ->where('segment_count', $segmentCount)
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS)
            ->orderByDesc('success_count')
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->safeEvidenceFromRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastFailureEvidence(
        int $supplierConnectionId,
        string $strategyCode,
        string $validatingCarrier,
        string $routePattern,
        string $tripType,
        int $segmentCount,
    ): ?array {
        $row = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', $supplierConnectionId)
            ->where('strategy_code', $strategyCode)
            ->where('validating_carrier', $validatingCarrier)
            ->where('route_pattern', $routePattern)
            ->where('trip_type', $tripType)
            ->where('segment_count', $segmentCount)
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_FAILURE)
            ->orderByDesc('updated_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->safeEvidenceFromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeEvidenceFromRow(SabreGdsPnrCreateStrategyEvidence $row): array
    {
        return [
            'last_success_booking_id' => $row->last_success_booking_id,
            'last_failed_booking_id' => $row->failed_booking_id,
            'last_success_at' => $row->last_success_at?->toIso8601String(),
            'last_failure_reason_code' => $row->safe_reason_code ?? $row->host_error_family,
            'host_error_family' => $row->host_error_family,
            'success_count' => (int) $row->success_count,
        ];
    }
}
