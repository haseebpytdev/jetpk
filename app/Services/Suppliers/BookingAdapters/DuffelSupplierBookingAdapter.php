<?php

namespace App\Services\Suppliers\BookingAdapters;

use App\Contracts\Suppliers\SupplierBookingInterface;
use App\Data\SupplierBookingResultData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Duffel\DuffelClient;
use App\Services\Suppliers\Duffel\DuffelOrderNormalizer;
use App\Services\Suppliers\Duffel\DuffelProviderException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;
use Throwable;

class DuffelSupplierBookingAdapter implements SupplierBookingInterface
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly DuffelOrderNormalizer $orderNormalizer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        if ($connection->provider !== SupplierProvider::Duffel) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: 'Supplier provider mismatch for Duffel booking.'
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: 'supplier_provider_mismatch',
                error_message: 'Supplier provider mismatch for Duffel booking.'
            );
        }

        if (! in_array($connection->environment, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: 'Duffel booking is only enabled in test mode.'
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: 'supplier_environment_not_allowed',
                error_message: 'Duffel booking is only enabled in test mode.'
            );
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (! isset($meta['validated_offer_snapshot']) && ! isset($meta['normalized_offer_snapshot'])) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: 'Validated offer snapshot is required before Duffel order creation.'
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: 'validated_offer_missing',
                error_message: 'Validated offer snapshot is required before Duffel order creation.'
            );
        }

        try {
            $response = $this->client->createOrder($booking, $connection, $actor);
            $normalized = $this->orderNormalizer->normalize($response);
            $correlationId = isset($normalized['correlation_id']) ? (string) $normalized['correlation_id'] : null;
            $durationMs = isset($normalized['duration_ms']) ? (int) $normalized['duration_ms'] : null;

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'success',
                durationMs: $durationMs,
                safeMessage: 'Duffel order created.',
                correlationId: $correlationId,
                meta: [
                    'order_id' => $normalized['supplier_reference'] ?? null,
                    'booking_reference' => $normalized['booking_reference'] ?? null,
                ]
            );

            return new SupplierBookingResultData(
                success: true,
                status: (string) ($normalized['status'] ?? 'created'),
                provider: $connection->provider->value,
                supplier_reference: isset($normalized['supplier_reference']) ? (string) $normalized['supplier_reference'] : null,
                pnr: isset($normalized['pnr']) ? (string) $normalized['pnr'] : null,
                safe_summary: (array) ($normalized['safe_summary'] ?? []),
                request_payload: SensitiveDataRedactor::redact([
                    'booking_id' => $booking->id,
                    'provider' => 'duffel',
                ]),
                response_payload: SensitiveDataRedactor::redact((array) ($normalized['response_payload'] ?? [])),
            );
        } catch (DuffelProviderException $exception) {
            $meta = array_merge(
                $exception->context,
                [
                    'error_code' => $exception->normalizedCode === 'supplier_request_invalid'
                        ? 'supplier_request_invalid'
                        : $exception->normalizedCode,
                    'reason_code' => $exception->normalizedCode === 'supplier_request_invalid'
                        ? 'supplier_request_invalid'
                        : $exception->normalizedCode,
                    'booking_id' => $booking->id,
                ],
            );

            if ($exception->normalizedCode === 'supplier_request_invalid') {
                Log::warning('duffel.create_order.supplier_request_invalid', [
                    'booking_id' => $booking->id,
                    'endpoint' => $meta['endpoint'] ?? null,
                    'request_context' => $meta['request_context'] ?? null,
                    'passenger_payload_included' => $meta['passenger_payload_included'] ?? null,
                    'supplier_offer_id_present' => $meta['supplier_offer_id_present'] ?? null,
                    'duffel_errors' => $meta['duffel_errors'] ?? [],
                ]);
            }

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: $exception->normalizedCode === 'supplier_request_invalid'
                    ? 'Duffel rejected the order request. Check payload/passengers/offer id.'
                    : $exception->safeMessage,
                meta: $meta,
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
                safe_summary: [
                    'provider_error' => $exception->normalizedCode,
                ]
            );
        } catch (Throwable) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: 'Duffel booking could not be created at this time.'
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: 'supplier_booking_failed',
                error_message: 'Duffel booking could not be created at this time.',
                safe_summary: [
                    'provider_error' => 'supplier_booking_failed',
                ]
            );
        }
    }
}
