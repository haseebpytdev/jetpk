<?php

namespace App\Support\Suppliers;

use App\Models\SupplierValidationStrategyEvidence;

/**
 * Record safe validation strategy certification evidence (no raw payload / PII).
 */
final class SupplierValidationStrategyEvidenceRecorder
{
    /**
     * @return array<string, mixed>|null
     */
    public function findLatestSuccess(int $supplierConnectionId, string $actionCode, string $strategyCode): ?array
    {
        $row = SupplierValidationStrategyEvidence::query()
            ->where('supplier_connection_id', $supplierConnectionId)
            ->where('action_code', $actionCode)
            ->where('strategy_code', $strategyCode)
            ->where('outcome', SupplierValidationStrategyEvidence::OUTCOME_SUCCESS)
            ->orderByDesc('last_success_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'strategy_code' => (string) $row->strategy_code,
            'action_code' => (string) $row->action_code,
            'success_count' => (int) $row->success_count,
            'last_success_at' => $row->last_success_at?->toIso8601String(),
            'route_pattern' => $row->route_pattern,
            'validating_carrier' => $row->validating_carrier,
        ];
    }
}
