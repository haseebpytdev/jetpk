<?php

namespace App\Support\Sabre;

use App\Services\Suppliers\Sabre\Core\SabreCapabilityMatrixService;

/**
 * Read-only Sabre architecture posture summaries for admin/staff UI (Phase S1E).
 *
 * Wraps {@see SabreCapabilityMatrixService} — no env reads, HTTP, or booking behavior changes.
 */
final class SabreCapabilityPosture
{
    public function __construct(
        private SabreCapabilityMatrixService $matrix = new SabreCapabilityMatrixService,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }
     */
    public function cancelPosture(): array
    {
        return $this->normalizePosture($this->matrix->get('gds_cancel'), 'gds_cancel');
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }
     */
    public function ticketingPosture(): array
    {
        return $this->normalizePosture($this->matrix->get('gds_ticketing'), 'gds_ticketing');
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }
     */
    public function pnrCreatePosture(): array
    {
        return $this->normalizePosture($this->matrix->get('gds_pnr_create'), 'gds_pnr_create');
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }
     */
    public function pnrRetrieveSyncPosture(): array
    {
        return $this->normalizePosture($this->matrix->get('gds_pnr_retrieve_sync'), 'gds_pnr_retrieve_sync');
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }>
     */
    public function mutationPolicySummary(): array
    {
        return $this->summaryForKeys([
            'gds_pnr_create',
            'gds_pnr_retrieve_sync',
            'gds_ticketing',
            'gds_cancel',
        ]);
    }

    /**
     * @return array{
     *     summary_label: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         status: string,
     *         manual_required: bool,
     *         production_allowed: bool,
     *         live_supplier_call_allowed: bool,
     *         notes: string
     *     }>
     * }
     */
    public function ndcPosture(): array
    {
        $keys = ['ndc_search', 'ndc_order_create', 'ndc_order_retrieve', 'ndc_cancel'];
        $items = $this->summaryForKeys($keys);

        return [
            'summary_label' => 'Unknown/disabled — not production',
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }
     */
    public function diagnosticsPosture(): array
    {
        return $this->normalizePosture($this->matrix->get('diagnostics'), 'diagnostics');
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }>
     */
    public function summaryForKeys(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[] = $this->normalizePosture($this->matrix->get($key), $key);
        }

        return $out;
    }

    /**
     * Admin/staff booking detail: safe posture block (no secrets).
     *
     * @return array{
     *     show: bool,
     *     gds_cancel: array<string, mixed>,
     *     gds_cancel_label: string,
     *     gds_ticketing: array<string, mixed>,
     *     gds_ticketing_label: string,
     *     ndc: array<string, mixed>,
     *     ndc_label: string,
     *     diagnostics: array<string, mixed>,
     *     diagnostics_label: string,
     *     staff_guidance: string
     * }
     */
    public function bookingViewSummary(): array
    {
        $cancel = $this->cancelPosture();
        $ticketing = $this->ticketingPosture();
        $ndc = $this->ndcPosture();
        $diagnostics = $this->diagnosticsPosture();

        return [
            'show' => true,
            'gds_cancel' => $cancel,
            'gds_cancel_label' => $this->architectureDisplayLabel($cancel),
            'gds_ticketing' => $ticketing,
            'gds_ticketing_label' => $this->architectureDisplayLabel($ticketing),
            'ndc' => $ndc,
            'ndc_label' => $ndc['summary_label'],
            'diagnostics' => $diagnostics,
            'diagnostics_label' => $this->architectureDisplayLabel($diagnostics),
            'staff_guidance' => 'GDS cancellation remains unresolved — staff manual review is required for supplier cancel outcomes. GDS ticketing is disabled; issue tickets manually. NDC paths are not production-certified.',
        ];
    }

    /**
     * @param  array<string, mixed>  $posture
     */
    public function architectureDisplayLabel(array $posture): string
    {
        $status = strtolower(trim((string) ($posture['status'] ?? 'unknown')));
        $manual = ($posture['manual_required'] ?? false) === true;

        $base = match ($status) {
            'unresolved' => 'Unresolved',
            'disabled' => 'Disabled',
            'unknown' => 'Unknown',
            'diagnostic_only' => 'Diagnostic only',
            'enabled' => 'Enabled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };

        if ($manual) {
            return $base.' — manual required';
        }

        if ($status === 'diagnostic_only') {
            return $base.' — not customer-facing';
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>|null  $cap
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     manual_required: bool,
     *     production_allowed: bool,
     *     live_supplier_call_allowed: bool,
     *     notes: string
     * }
     */
    private function normalizePosture(?array $cap, string $fallbackKey): array
    {
        if ($cap === null) {
            return [
                'key' => $fallbackKey,
                'label' => ucfirst(str_replace('_', ' ', $fallbackKey)),
                'status' => 'unknown',
                'manual_required' => false,
                'production_allowed' => false,
                'live_supplier_call_allowed' => false,
                'notes' => '',
            ];
        }

        return [
            'key' => (string) ($cap['key'] ?? $fallbackKey),
            'label' => (string) ($cap['label'] ?? $fallbackKey),
            'status' => (string) ($cap['status'] ?? 'unknown'),
            'manual_required' => ($cap['manual_required'] ?? false) === true,
            'production_allowed' => ($cap['production_allowed'] ?? false) === true,
            'live_supplier_call_allowed' => ($cap['live_supplier_call_allowed'] ?? false) === true,
            'notes' => (string) ($cap['notes'] ?? ''),
        ];
    }
}
