<?php

namespace App\Support\Suppliers;

use App\Models\Booking;

/**
 * Read-only digest for supplier validation / freshness strategies (no live HTTP, no booking mutation).
 */
final class SupplierValidationStrategyDigest
{
    public function __construct(
        protected SupplierValidationStrategyRegistry $registry,
        protected SupplierValidationStrategySelector $selector,
        protected SupplierValidationStrategyEvidenceRecorder $evidenceRecorder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildBookingSummary(Booking $booking, string $action): array
    {
        $booking->loadMissing(['passengers', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $selection = $this->selector->selectForBooking($booking, $action);

        return [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'action' => strtolower(trim($action)),
            'selected_validation_strategy' => $selection['selected_strategy'] ?? null,
            'selection_reason' => $selection['selection_reason'] ?? null,
            'eligible_strategies' => $selection['eligible_strategies'] ?? [],
            'blocked_strategies' => $selection['blocked_strategies'] ?? [],
            'fallback_available' => (bool) ($selection['fallback_available'] ?? false),
            'automatic_multi_strategy_retry' => false,
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 0) ?: null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildCandidateDigests(Booking $booking, string $action, ?array $selection = null): array
    {
        $selection ??= $this->selector->selectForBooking($booking, $action);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $selected = (string) ($selection['selected_strategy'] ?? '');
        $eligible = is_array($selection['eligible_strategies'] ?? null) ? $selection['eligible_strategies'] : [];
        $blocked = is_array($selection['blocked_strategies'] ?? null) ? $selection['blocked_strategies'] : [];
        $blockedMap = [];
        foreach ($blocked as $row) {
            if (! is_array($row)) {
                continue;
            }
            $blockedMap[(string) ($row['strategy_code'] ?? '')] = is_array($row['blockers'] ?? null)
                ? $row['blockers']
                : [];
        }

        $digests = [];
        foreach ($this->registry->supportedCodesForAction($action) as $code) {
            $definition = $this->registry->get($code);
            $knownGood = $connId > 0
                ? $this->evidenceRecorder->findLatestSuccess($connId, $action, $code)
                : null;
            $digests[] = [
                'strategy_code' => $code,
                'provider' => $definition['provider'] ?? null,
                'distribution_channel' => $definition['distribution_channel'] ?? null,
                'endpoint_path' => $definition['endpoint_path'] ?? null,
                'payload_schema' => $definition['payload_schema'] ?? null,
                'automatic_allowed' => (bool) ($definition['automatic_allowed'] ?? false),
                'admin_confirmed_fallback_allowed' => (bool) ($definition['admin_confirmed_fallback_allowed'] ?? false),
                'eligible' => in_array($code, $eligible, true),
                'blocked' => array_key_exists($code, $blockedMap),
                'blockers' => $blockedMap[$code] ?? [],
                'selected' => $code === $selected,
                'known_good_evidence' => $knownGood,
            ];
        }

        return $digests;
    }
}
