<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin/staff Sabre GDS unticketed PNR cancellation panel (stored metadata only in view).
 */
final class AdminSabreGdsCancelPanelsPresenter
{
    public function __construct(
        private readonly SabreGdsCancelReadiness $readiness,
    ) {}

    /**
     * @return array{
     *     show: bool,
     *     title: string,
     *     action_state: string,
     *     action_label: string,
     *     admin_message: string,
     *     customer_message: string,
     *     can_execute: bool,
     *     rows: list<array{label: string, value: string}>
     * }
     */
    public function gdsCancelPanel(Booking $booking): array
    {
        $empty = [
            'show' => false,
            'title' => 'Sabre GDS Cancellation',
            'action_state' => SabreGdsCancelReadiness::ACTION_NOT_ELIGIBLE,
            'action_label' => 'Not eligible',
            'admin_message' => '',
            'customer_message' => '',
            'can_execute' => false,
            'rows' => [],
        ];

        try {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
            if ($provider !== SupplierProvider::Sabre->value) {
                return $empty;
            }

            $readiness = $this->readiness->evaluate($booking);
            if (($readiness['eligible_provider'] ?? false) !== true && ($readiness['cancelled'] ?? false) !== true) {
                return $empty;
            }

            $rows = [
                ['label' => 'Action', 'value' => (string) ($readiness['action_label'] ?? 'Not eligible')],
                ['label' => 'Admin gate', 'value' => ($readiness['admin_live_gate_enabled'] ?? false) ? 'enabled' : 'disabled'],
                ['label' => 'Ticketed', 'value' => ($readiness['ticketed'] ?? false) ? 'yes' : 'no'],
                ['label' => 'In progress', 'value' => ($readiness['in_progress'] ?? false) ? 'yes' : 'no'],
                ['label' => 'Cancelled', 'value' => ($readiness['cancelled'] ?? false) ? 'yes' : 'no'],
            ];

            if (($readiness['stored_classification'] ?? null) !== null) {
                $rows[] = ['label' => 'Classification', 'value' => (string) $readiness['stored_classification']];
            }
            if (($readiness['post_cancel_segment_count'] ?? null) !== null) {
                $rows[] = ['label' => 'Post-cancel segments', 'value' => (string) $readiness['post_cancel_segment_count']];
            }
            $segmentStatuses = is_array($readiness['stored_segment_statuses'] ?? null)
                ? $readiness['stored_segment_statuses']
                : [];
            if ($segmentStatuses !== []) {
                $rows[] = ['label' => 'Segment status', 'value' => implode(', ', $segmentStatuses)];
            }
            $blockers = is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [];
            if ($blockers !== []) {
                $rows[] = ['label' => 'Blockers', 'value' => implode(', ', $blockers)];
            }

            return [
                'show' => true,
                'title' => 'Sabre GDS Cancellation',
                'action_state' => (string) ($readiness['action_state'] ?? SabreGdsCancelReadiness::ACTION_NOT_ELIGIBLE),
                'action_label' => (string) ($readiness['action_label'] ?? 'Not eligible'),
                'admin_message' => (string) ($readiness['admin_message'] ?? ''),
                'customer_message' => (string) ($readiness['customer_message'] ?? ''),
                'can_execute' => (bool) ($readiness['can_execute'] ?? false),
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            Log::warning('sabre_gds_cancel_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return $empty;
        }
    }
}
