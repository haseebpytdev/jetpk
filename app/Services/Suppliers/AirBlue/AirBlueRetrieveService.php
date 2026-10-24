<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use Illuminate\Support\Facades\Log;

class AirBlueRetrieveService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function retrieveAndSync(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        return $this->retrieveOta($booking, $connection, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function retrieveOta(Booking $booking, SupplierConnection $connection, array $context): array
    {
        $pnr = trim((string) ($context['pnr'] ?? $booking->pnr ?? $booking->supplier_reference ?? ''));
        if ($pnr === '') {
            return ['synced' => false, 'reason' => 'missing_order_context'];
        }

        // Zapways v2.06/v3.00: Instance is optional for a normal/current PNR Read.
        // When a caller explicitly asks for a historical/pending instance, fail closed
        // if that instance is unavailable rather than silently retrieving the current PNR.
        $specificInstanceRequested = $this->isSpecificInstanceReadRequested($context);
        $instance = $this->resolveReadInstance($context);
        if ($specificInstanceRequested && $instance === null) {
            return ['synced' => false, 'reason' => 'missing_order_context'];
        }

        try {
            $config = $this->configResolver->resolveOta($connection);
            $xml = $this->otaXmlBuilder->buildReadRequest($config, $pnr, $instance);
            $response = $this->client->callOta($connection, 'read', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'read',
            ]);
            $normalized = $this->otaNormalizer->normalizeRetrieveResponse($response, $context);
            $this->mergeSync($booking, $normalized);

            return ['synced' => true, 'data' => $normalized];
        } catch (AirBlueException $exception) {
            $diagnostic = $this->safeFailureDiagnostic($exception, $booking, $context);

            Log::channel('air-blue')->warning('airblue.retrieve.failed', [
                'booking_id' => $booking->id,
                'error_code' => $exception->normalizedCode,
                'diagnostic' => $diagnostic,
            ]);

            return [
                'synced' => false,
                'reason' => $exception->normalizedCode,
                'diagnostic' => $diagnostic,
            ];
        }
    }

    /**
     * Preserve only the provider diagnostics needed to classify a failed Read.
     * Never include raw XML, PNR, Instance, credentials, or passenger data.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safeFailureDiagnostic(AirBlueException $exception, Booking $booking, array $context): array
    {
        $diagnostic = [];

        $correlationId = $exception->context['correlation_id'] ?? null;
        if (is_string($correlationId) && trim($correlationId) !== '') {
            $diagnostic['correlation_id'] = substr(trim($correlationId), 0, 80);
        }

        $httpStatus = $exception->context['http_status'] ?? $exception->httpStatus ?? null;
        if (is_numeric($httpStatus)) {
            $diagnostic['http_status'] = (int) $httpStatus;
        }

        $faultClass = $exception->context['fault_class'] ?? null;
        if (is_string($faultClass) && trim($faultClass) !== '') {
            $diagnostic['fault_class'] = substr(trim($faultClass), 0, 80);
        }

        $faultSnippet = $exception->context['fault_snippet'] ?? null;
        if (is_string($faultSnippet) && trim($faultSnippet) !== '') {
            $sensitiveReferences = array_values(array_filter(array_unique([
                trim((string) ($booking->pnr ?? '')),
                trim((string) ($booking->supplier_reference ?? '')),
                trim((string) ($context['pnr'] ?? '')),
                trim((string) ($context['instance'] ?? '')),
            ]), static fn (string $value): bool => strlen($value) >= 3));

            $faultSnippet = html_entity_decode($faultSnippet, ENT_QUOTES | ENT_XML1);
            foreach ($sensitiveReferences as $reference) {
                $faultSnippet = str_ireplace($reference, '[REDACTED_REFERENCE]', $faultSnippet);
            }
            $faultSnippet = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $faultSnippet) ?? $faultSnippet;
            $faultSnippet = preg_replace('/[A-Za-z0-9+\\/=]{40,}/', '[REDACTED]', $faultSnippet) ?? $faultSnippet;
            $faultSnippet = preg_replace('/\\s+/', ' ', $faultSnippet) ?? $faultSnippet;

            $diagnostic['fault_snippet'] = substr(trim($faultSnippet), 0, 220);
        }

        return $diagnostic;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveReadInstance(array $context): ?string
    {
        if (! $this->isSpecificInstanceReadRequested($context)) {
            return null;
        }

        $instance = trim((string) ($context['instance'] ?? ''));

        return $instance !== '' ? $instance : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isSpecificInstanceReadRequested(array $context): bool
    {
        $mode = strtolower(trim((string) ($context['retrieve_mode'] ?? $context['read_mode'] ?? '')));

        return filter_var($context['read_specific_instance'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || in_array($mode, ['specific_instance', 'historical', 'pending_instance'], true);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function mergeSync(Booking $booking, array $normalized): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        foreach ($normalized as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $existing[$key] = $value;
        }
        $existing['last_sync_at'] = now()->toIso8601String();

        // Successful retrieve with PNR/instance clears uncertain local outcome so ops can proceed.
        $resolvedPnr = trim((string) ($existing['pnr'] ?? ''));
        $resolvedInstance = trim((string) ($existing['instance'] ?? ''));
        if (($existing['booking_outcome'] ?? null) === 'uncertain' && $resolvedPnr !== '' && $resolvedInstance !== '') {
            $existing['booking_outcome'] = 'reconciled';
            $existing['supplier_outcome_unknown'] = false;
            $existing['reconciled_at'] = now()->toIso8601String();

            SupplierBookingAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', SupplierProvider::Airblue->value)
                ->where('action', 'create_pnr')
                ->where('status', 'uncertain')
                ->update([
                    'status' => 'reconciled',
                    'completed_at' => now(),
                    'safe_summary' => [
                        'reason_code' => 'booking_outcome_reconciled_via_retrieve',
                        'requires_retrieve_before_retry' => false,
                    ],
                ]);

            SupplierBooking::query()
                ->where('booking_id', $booking->id)
                ->where('provider', SupplierProvider::Airblue->value)
                ->where('status', 'pending_reconciliation')
                ->update([
                    'status' => 'confirmed',
                    'supplier_reference' => $resolvedPnr,
                    'pnr' => $resolvedPnr,
                ]);
        }

        $meta['airblue_context'] = $existing;
        $meta['supplier_provider'] = SupplierProvider::Airblue->value;
        $booking->meta = $meta;
        if (($normalized['order_id'] ?? '') !== '') {
            $booking->supplier_reference = (string) $normalized['order_id'];
        } elseif (($normalized['pnr'] ?? '') !== '') {
            $booking->supplier_reference = (string) $normalized['pnr'];
        }
        $booking->save();
    }
}
