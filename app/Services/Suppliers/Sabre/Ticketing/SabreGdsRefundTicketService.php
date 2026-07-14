<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\Response;

/**
 * GDS refund workflow — live path when configured; otherwise records manual Red Workspace refund request (Binham parity).
 */
final class SabreGdsRefundTicketService
{
    public function __construct(
        private readonly SabreGdsTicketServicingReadiness $readiness,
        private readonly SabreGdsTicketingAuditLogger $auditLogger,
        private readonly SabreClient $sabreClient,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function refundTicket(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        ?string $ticketNumber = null,
        array $options = [],
    ): TicketingResultData {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $confirm = isset($options['confirm']) ? (string) $options['confirm'] : null;
        $refundAmount = isset($options['refund_amount']) ? (float) $options['refund_amount'] : null;
        $refundReason = trim((string) ($options['refund_reason'] ?? 'Customer requested refund'));

        $evaluation = $this->readiness->evaluateRefund($booking, $ticketNumber);
        $confirmOk = SabreGdsTicketServicingReadiness::confirmPhraseMatches($booking, 'refund', $ticketNumber, $confirm);

        if ($dryRun || ! $confirmOk) {
            return new TicketingResultData(
                success: false,
                status: $dryRun ? 'dry_run' : 'blocked',
                provider: SupplierProvider::Sabre->value,
                error_code: $dryRun ? 'dry_run' : 'refund_blocked',
                error_message: $dryRun ? 'Dry-run only — no refund attempted.' : 'Refund blocked — confirm phrase required.',
                safe_summary: array_merge($evaluation, [
                    'live_supplier_call_attempted' => false,
                    'workflow' => 'manual_or_live',
                ]),
            );
        }

        $liveEnabled = (bool) config('suppliers.sabre.refund_live_call_enabled', false);
        if (! $liveEnabled) {
            return $this->recordManualRefundRequest($booking, $ticketNumber, $refundAmount, $refundReason);
        }

        $path = (string) config('suppliers.sabre.refund_flight_tickets_path', '/v1/trip/orders/refundFlightTickets');
        $pnr = trim((string) ($booking->pnr ?? ''));
        $ticket = $ticketNumber ?? (string) ($evaluation['ticket_number'] ?? '');

        $payload = array_filter([
            'confirmationId' => $pnr !== '' ? $pnr : null,
            'tickets' => $ticket !== '' ? [['number' => $ticket]] : null,
        ]);

        try {
            $this->auditLogger->log('sabre.gds_refund.live_attempt', $booking, $actor, [
                'endpoint_path' => $path,
            ]);

            /** @var Response $response */
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $payload);
            $json = is_array($response->json()) ? $response->json() : [];

            if (! $response->successful()) {
                return $this->recordManualRefundRequest($booking, $ticketNumber, $refundAmount, $refundReason, [
                    'live_attempt_failed' => true,
                    'http_status' => $response->status(),
                ]);
            }

            $this->persistRefundState($booking, $ticket, $json, 'refunded');

            return new TicketingResultData(
                success: true,
                status: 'refunded',
                provider: SupplierProvider::Sabre->value,
                safe_summary: [
                    'live_supplier_call_attempted' => true,
                    'workflow' => 'live_api',
                    'ticket_number' => $ticket,
                ],
                response_payload: SensitiveDataRedactor::redact($json),
            );
        } catch (\Throwable) {
            return $this->recordManualRefundRequest($booking, $ticketNumber, $refundAmount, $refundReason, [
                'live_attempt_failed' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function recordManualRefundRequest(
        Booking $booking,
        ?string $ticketNumber,
        ?float $refundAmount,
        string $refundReason,
        array $extra = [],
    ): TicketingResultData {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $totalAmount = (float) ($booking->revalidated_fare_total ?? $booking->selected_fare_total ?? 0);
        if ($refundAmount === null || $refundAmount <= 0) {
            $refundAmount = $totalAmount;
        }

        $details = SensitiveDataRedactor::redact(array_merge([
            'status' => 'pending_manual_processing',
            'requested_at' => now()->toIso8601String(),
            'ticket_number' => $ticketNumber,
            'refund_amount' => $refundAmount,
            'refund_reason' => $refundReason,
            'note' => 'Sabre refunds typically require manual processing through Sabre Red Workspace when live API unavailable.',
        ], $extra));

        $meta['sabre_refund'] = $details;
        $booking->meta = $meta;
        $booking->refund_status = 'refund_pending';
        $booking->save();

        return new TicketingResultData(
            success: true,
            status: 'refund_pending',
            provider: SupplierProvider::Sabre->value,
            safe_summary: [
                'live_supplier_call_attempted' => false,
                'workflow' => 'provider_unsupported_manual',
                'refund_status' => 'refund_pending',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function persistRefundState(Booking $booking, string $ticketNumber, array $json, string $status): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['sabre_refund'] = SensitiveDataRedactor::redact([
            'status' => $status,
            'refunded_at' => now()->toIso8601String(),
            'ticket_number' => $ticketNumber,
            'response_digest' => md5(json_encode($json)),
        ]);
        $booking->meta = $meta;
        $booking->refund_status = $status;
        $booking->save();
    }
}
