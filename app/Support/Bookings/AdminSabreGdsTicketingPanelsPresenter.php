<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcStatusService;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin/staff Sabre GDS ticketing + NDC diagnostic panels (no raw payloads).
 */
final class AdminSabreGdsTicketingPanelsPresenter
{
    public function __construct(
        private readonly SabreGdsTicketingReadiness $ticketingReadiness,
        private readonly SabreNdcStatusService $ndcStatusService,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @return array{show: bool, title: string, rows: list<array{label: string, value: string}>}
     */
    public function gdsTicketingPanel(Booking $booking): array
    {
        $empty = ['show' => false, 'title' => 'Sabre GDS Ticketing', 'rows' => []];

        try {
            if (! app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS)) {
                return $empty;
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];
            $readiness = $this->ticketingReadiness->evaluate($booking, ['dry_run' => true]);
            $pnrCancelled = ($readiness['pnr_cancelled_or_released'] ?? false) === true;
            $rows = [
                ['label' => 'Action', 'value' => (string) ($readiness['action_label'] ?? 'Not eligible')],
                ['label' => 'Eligible', 'value' => ($readiness['eligible'] ?? false) ? 'Yes' : 'No'],
                ['label' => 'Live call allowed', 'value' => ($readiness['live_supplier_call_allowed'] ?? false) ? 'Yes' : 'No'],
                ['label' => 'Itinerary synced', 'value' => ($readiness['itinerary_synced'] ?? false) ? 'yes' : 'no'],
                ['label' => 'PNR released/cancelled', 'value' => $pnrCancelled ? 'yes' : 'no'],
                ['label' => 'Ticketing env', 'value' => (bool) config('suppliers.sabre.ticketing_enabled', false) ? 'enabled' : 'disabled'],
                ['label' => 'Live call env', 'value' => (bool) config('suppliers.sabre.ticketing_live_call_enabled', false) ? 'enabled' : 'disabled'],
                ['label' => 'Confirm phrase', 'value' => (string) ($readiness['confirm_phrase'] ?? '')],
                ['label' => 'Blockers', 'value' => $this->formatList($this->displayBlockers($readiness['blockers'] ?? [], $pnrCancelled))],
            ];

            $ticketNumbers = $booking->tickets->pluck('ticket_number')->filter()->values()->all();
            if ($ticketNumbers !== []) {
                $rows[] = ['label' => 'Issued tickets', 'value' => implode(', ', array_map('strval', $ticketNumbers))];
            }

            $documents = data_get($meta, 'sabre_ticket_documents', []);
            if (is_array($documents) && $documents !== []) {
                $docLines = [];
                foreach (array_slice($documents, 0, 5) as $doc) {
                    if (! is_array($doc)) {
                        continue;
                    }
                    $docLines[] = trim((string) ($doc['ticket_number'] ?? ''))
                        .($doc['ticket_status'] ? ' ('.$doc['ticket_status'].')' : '');
                }
                if ($docLines !== []) {
                    $rows[] = ['label' => 'Ticket documents', 'value' => implode('; ', $docLines)];
                }
            }

            $revalidation = data_get($meta, 'sabre_revalidation', []);
            if (is_array($revalidation) && ($revalidation['success'] ?? false) === true) {
                $rows[] = ['label' => 'Revalidated', 'value' => (string) ($revalidation['revalidated_at'] ?? 'yes')];
            }

            $attempt = $booking->latestTicketingAttempt;
            if ($attempt !== null) {
                $rows[] = ['label' => 'Latest attempt', 'value' => (string) ($attempt->status ?? '—')];
                if ($attempt->error_code) {
                    $rows[] = ['label' => 'Latest error', 'value' => (string) $attempt->error_code];
                }
            }

            return [
                'show' => true,
                'title' => 'Sabre GDS Ticketing',
                'action_state' => (string) ($readiness['action_state'] ?? SabreGdsTicketingReadiness::ACTION_NOT_ELIGIBLE),
                'action_label' => (string) ($readiness['action_label'] ?? 'Not eligible'),
                'admin_message' => (string) ($readiness['admin_message'] ?? ''),
                'customer_message' => (string) ($readiness['customer_message'] ?? ''),
                'can_execute' => (bool) ($readiness['can_execute'] ?? false),
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            Log::warning('sabre_gds_ticketing_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return $empty;
        }
    }

    /**
     * @return array{show: bool, title: string, rows: list<array{label: string, value: string}>}
     */
    public function ndcOrderPanel(Booking $booking): array
    {
        $empty = ['show' => false, 'title' => 'Sabre NDC Order', 'rows' => []];

        try {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
            $isNdc = $this->platformModuleEnforcer->isSabreNdcDistributionChannel(
                $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta)
            );
            $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];

            if ($provider !== SupplierProvider::Sabre->value && $ndcContext === [] && ! $isNdc) {
                return $empty;
            }

            $rows = [
                ['label' => 'NDC channel', 'value' => $isNdc ? 'Yes' : 'No'],
                ['label' => 'NDC env', 'value' => (bool) config('suppliers.sabre.ndc.enabled', false) ? 'enabled' : 'disabled'],
                ['label' => 'Order create env', 'value' => (bool) config('suppliers.sabre.ndc.order_create_enabled', false) ? 'enabled' : 'disabled'],
                ['label' => 'Order ID', 'value' => $this->maskId((string) ($ndcContext['order_id'] ?? ''))],
                ['label' => 'Owner code', 'value' => (string) ($ndcContext['owner_code'] ?? '—')],
                ['label' => 'Order status', 'value' => (string) ($ndcContext['order_status'] ?? '—')],
                ['label' => 'PNR locator', 'value' => (string) ($ndcContext['pnr_locator'] ?? $booking->pnr ?? '—')],
            ];

            return ['show' => true, 'title' => 'Sabre NDC Order', 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::warning('sabre_ndc_order_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
            ]);

            return $empty;
        }
    }

    /**
     * @return array{show: bool, title: string, rows: list<array{label: string, value: string}>}
     */
    public function ndcStatusPanel(): array
    {
        try {
            $status = $this->ndcStatusService->status(null);

            return [
                'show' => true,
                'title' => 'Sabre NDC Status',
                'rows' => [
                    ['label' => 'NDC allowed', 'value' => ($status['effective_ndc_enabled'] ?? false) ? 'yes' : 'no'],
                    ['label' => 'GDS suppressed', 'value' => ($status['gds_suppressed'] ?? false) ? 'yes' : 'no'],
                    ['label' => 'Selected lanes', 'value' => implode(', ', $status['selected_sabre_lanes'] ?? [])],
                    ['label' => 'Shared credentials', 'value' => ($status['shared_credentials_present'] ?? false) ? 'present' : 'missing'],
                    ['label' => 'Blockers', 'value' => $this->formatList($status['blockers'] ?? [])],
                ],
            ];
        } catch (\Throwable) {
            return [
                'show' => true,
                'title' => 'Sabre NDC Status',
                'rows' => [['label' => 'Status', 'value' => 'Unavailable']],
            ];
        }
    }

    /**
     * @param  list<mixed>  $blockers
     * @return list<string>
     */
    private function displayBlockers(array $blockers, bool $pnrCancelled): array
    {
        $filtered = array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $blockers,
        )));

        if (! $pnrCancelled) {
            return $filtered;
        }

        $suppress = ['itinerary_not_synced', 'e10_pnr_itinerary_synced'];

        return array_values(array_filter(
            $filtered,
            static fn (string $code) => ! in_array($code, $suppress, true),
        ));
    }

    /**
     * @param  list<mixed>  $items
     */
    private function formatList(array $items): string
    {
        $filtered = array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $items,
        )));

        return $filtered === [] ? '—' : implode(', ', $filtered);
    }

    private function maskId(string $id): string
    {
        $id = trim($id);

        return $id === '' ? '—' : (strlen($id) <= 6 ? $id : substr($id, 0, 4).'...');
    }
}
