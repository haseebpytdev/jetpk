<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Retrieve GDS ticket documents via getBooking (safe metadata only).
 */
final class SabreGdsTicketDocumentService
{
    public function __construct(
        private readonly SabreGdsTicketDocumentNormalizer $normalizer,
        private readonly SabreClient $sabreClient,
        private readonly SabreGdsTicketingAuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function retrieve(Booking $booking, SupplierConnection $connection, bool $dryRun = true): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));

        $base = [
            'booking_id' => $booking->id,
            'provider' => $provider,
            'pnr_present' => $pnr !== '',
            'live_supplier_call_attempted' => false,
            'documents' => [],
            'blockers' => [],
        ];

        if ($provider !== SupplierProvider::Sabre->value) {
            $base['blockers'][] = 'supplier_not_sabre';

            return $base;
        }

        if ($pnr === '') {
            $base['blockers'][] = 'missing_pnr';

            return $base;
        }

        if ($connection->provider !== SupplierProvider::Sabre) {
            $base['blockers'][] = 'connection_not_sabre';

            return $base;
        }

        if ($dryRun || ! (bool) config('suppliers.sabre.ticket_documents_live_retrieve_enabled', false)) {
            $cached = data_get($meta, 'sabre_ticket_documents');
            if (is_array($cached) && $cached !== []) {
                $base['documents'] = $cached;
                $base['source'] = 'cached_meta';
            } else {
                $base['blockers'][] = $dryRun ? 'dry_run_only' : 'live_retrieve_disabled';
            }

            return $base;
        }

        $path = (string) config('suppliers.sabre.get_booking_path', '/v1/trip/orders/getBooking');

        try {
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, [
                'confirmationId' => $pnr,
            ]);
            $json = $response->json();
            $json = is_array($json) ? $json : [];

            $base['live_supplier_call_attempted'] = true;

            if (! $response->successful()) {
                $base['blockers'][] = 'get_booking_http_'.$response->status();

                return $base;
            }

            $documents = $this->normalizer->normalizeFromGetBooking($json);
            $base['documents'] = $documents;
            $base['document_count'] = count($documents);

            if ($documents !== []) {
                $meta['sabre_ticket_documents'] = $documents;
                $booking->meta = $meta;
                $booking->save();
            }

            $this->auditLogger->log('sabre.gds_ticket_documents.retrieved', $booking, null, [
                'document_count' => count($documents),
            ]);
        } catch (\Throwable $exception) {
            $base['blockers'][] = 'retrieve_unexpected';
            $base['safe_error'] = SensitiveDataRedactor::redact([
                'exception' => $exception::class,
            ]);
        }

        return $base;
    }
}
