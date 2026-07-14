<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\Response;

/**
 * GDS ticket void — attempts configured voidFlightTickets path; falls back to Trip Orders cancel for unticketed.
 */
final class SabreGdsVoidTicketService
{
    public function __construct(
        private readonly SabreGdsTicketServicingReadiness $readiness,
        private readonly SabreGdsTicketingAuditLogger $auditLogger,
        private readonly SabreClient $sabreClient,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function voidTicket(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        ?string $ticketNumber = null,
        array $options = [],
    ): TicketingResultData {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $confirm = isset($options['confirm']) ? (string) $options['confirm'] : null;

        $evaluation = $this->readiness->evaluateVoid($booking, $ticketNumber);
        $confirmOk = SabreGdsTicketServicingReadiness::confirmPhraseMatches($booking, 'void', $ticketNumber, $confirm);

        if ($dryRun || ! $confirmOk || ! (bool) config('suppliers.sabre.void_live_call_enabled', false)) {
            return new TicketingResultData(
                success: false,
                status: $dryRun ? 'dry_run' : 'blocked',
                provider: SupplierProvider::Sabre->value,
                error_code: $dryRun ? 'dry_run' : 'void_blocked',
                error_message: $dryRun ? 'Dry-run only — no void attempted.' : 'Void blocked by gates.',
                safe_summary: array_merge($evaluation, [
                    'live_supplier_call_attempted' => false,
                    'confirm_matches' => $confirmOk,
                ]),
            );
        }

        $path = (string) config('suppliers.sabre.void_flight_tickets_path', '/v1/trip/orders/voidFlightTickets');
        $pnr = trim((string) ($booking->pnr ?? ''));
        $ticket = $ticketNumber ?? (string) ($evaluation['ticket_number'] ?? '');

        $payload = array_filter([
            'confirmationId' => $pnr !== '' ? $pnr : null,
            'tickets' => $ticket !== '' ? [['number' => $ticket]] : null,
        ]);

        try {
            $this->auditLogger->log('sabre.gds_void.live_attempt', $booking, $actor, [
                'endpoint_path' => $path,
            ]);

            /** @var Response $response */
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $payload);
            $json = is_array($response->json()) ? $response->json() : [];

            if (! $response->successful()) {
                return new TicketingResultData(
                    success: false,
                    status: 'failed',
                    provider: SupplierProvider::Sabre->value,
                    error_code: 'void_http_'.$response->status(),
                    error_message: 'Sabre void request failed.',
                    safe_summary: ['live_supplier_call_attempted' => true],
                    response_payload: SensitiveDataRedactor::redact($json),
                );
            }

            $this->persistVoidState($booking, $ticket, $json);

            return new TicketingResultData(
                success: true,
                status: 'voided',
                provider: SupplierProvider::Sabre->value,
                safe_summary: [
                    'live_supplier_call_attempted' => true,
                    'ticket_number' => $ticket,
                ],
                response_payload: SensitiveDataRedactor::redact($json),
            );
        } catch (\Throwable $exception) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Sabre->value,
                error_code: 'void_unexpected',
                error_message: 'Void failed; admin review required.',
                safe_summary: ['live_supplier_call_attempted' => true],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function persistVoidState(Booking $booking, string $ticketNumber, array $json): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['sabre_void'] = SensitiveDataRedactor::redact([
            'voided_at' => now()->toIso8601String(),
            'ticket_number' => $ticketNumber,
            'response_digest' => md5(json_encode($json)),
        ]);
        $booking->meta = $meta;
        $booking->ticketing_status = 'voided';
        $booking->save();

        if ($ticketNumber !== '') {
            BookingTicket::query()
                ->where('booking_id', $booking->id)
                ->where('ticket_number', $ticketNumber)
                ->update(['status' => 'voided']);
        }
    }
}
