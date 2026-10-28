<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Illuminate\Support\Facades\Log;

/**
 * AirBlue Zapways v3 seat map and ancillary item operations.
 */
class AirBlueAncillaryService
{
    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueClient $client,
        private readonly AirBlueOtaXmlBuilder $xmlBuilder,
        private readonly AirBlueOtaXmlParser $xmlParser,
        private readonly AirBlueSeatModifyOutcomeGate $seatModifyOutcomeGate,
    ) {}

    public function isSupported(SupplierConnection $connection): bool
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return AirBlueZapwaysProtocolVersion::fromCredentials($credentials)->isV3();
    }

    /**
     * @param  array<string, mixed>  $seatMapContext
     * @return array<string, mixed>
     */
    public function fetchSeatMap(SupplierConnection $connection, array $seatMapContext): array
    {
        if (! $this->isSupported($connection)) {
            throw new AirBlueValidationException('ancillary_unsupported', 422, 'Seat map is only available on AirBlue Zapways OTA v3.0.');
        }

        $config = $this->configResolver->resolveOta($connection);
        $xml = $this->xmlBuilder->buildAirSeatMapRequest($config, $seatMapContext);
        $response = $this->client->callOta($connection, 'air_seat_map', $xml, ['request_context' => 'air_seat_map']);
        $parsed = is_array($response['parsed'] ?? null) ? $response['parsed'] : [];

        return [
            'seat_maps' => is_array($parsed['seat_maps'] ?? null) ? $parsed['seat_maps'] : [],
            'protocol_version' => $config['protocol_version'],
        ];
    }

    /**
     * @param  array<string, mixed>  $ancillaryContext
     * @return array<string, mixed>
     */
    public function fetchAncillaryItems(SupplierConnection $connection, array $ancillaryContext): array
    {
        if (! $this->isSupported($connection)) {
            throw new AirBlueValidationException('ancillary_unsupported', 422, 'Ancillary items are only available on AirBlue Zapways OTA v3.0.');
        }

        $config = $this->configResolver->resolveOta($connection);
        $xml = $this->xmlBuilder->buildAirAncillaryItemsRequest($config, $ancillaryContext);
        $response = $this->client->callOta($connection, 'air_ancillary_items', $xml, ['request_context' => 'air_ancillary_items']);
        $parsed = is_array($response['parsed'] ?? null) ? $response['parsed'] : [];

        return [
            'ancillary_items' => is_array($parsed['ancillary_items'] ?? null) ? $parsed['ancillary_items'] : [],
            'protocol_version' => $config['protocol_version'],
        ];
    }

    /**
     * Type=5 seat modify. Provider Success without SeatRequest is rejected.
     *
     * Optional `$modifyContext['booking']` (Booking) enables durable uncertain-after-send protection.
     *
     * @param  array<string, mixed>  $modifyContext
     * @return array{seats: list<array<string, mixed>>, protocol_version: string}
     */
    public function modifySeats(SupplierConnection $connection, array $modifyContext): array
    {
        if (! $this->isSupported($connection)) {
            throw new AirBlueValidationException('ancillary_unsupported', 422, 'Seat modify is only available on AirBlue Zapways OTA v3.0.');
        }

        $booking = $this->resolveBooking($modifyContext);
        if ($booking !== null && $this->isSeatModifyUncertain($booking)) {
            throw new AirBlueValidationException(
                'seat_modify_outcome_uncertain',
                409,
                'Prior seat modify mutation is uncertain; reconcile with Read only — do not retry Type=5.',
            );
        }

        $config = $this->configResolver->resolveOta($connection);
        $wireContext = $modifyContext;
        unset($wireContext['booking'], $wireContext['booking_id']);
        $wireContext['modification_type'] = (string) ($wireContext['modification_type'] ?? '5');
        $xml = $this->xmlBuilder->buildAirBookModifyRequest($config, $wireContext);

        $expected = $this->expectedSeatFromContext($wireContext);
        $transmitted = false;

        try {
            $transmitted = true;
            $response = $this->client->callOta($connection, 'air_book_modify', $xml, [
                'request_context' => 'air_book_modify_seat',
                'booking_id' => $booking?->id,
            ]);

            $this->seatModifyOutcomeGate->assertSeatAttached($response, $expected !== null ? [
                'row_number' => $expected['row_number'],
                'seat_number' => $expected['seat_number'],
            ] : null);

            if ($booking !== null) {
                $this->persistSeatModifySuccess($booking, $expected);
            }

            return [
                'seats' => $this->seatModifyOutcomeGate->reservedSeats($response),
                'protocol_version' => (string) $config['protocol_version'],
            ];
        } catch (AirBlueValidationException $exception) {
            // Pre-send validation / definitive outcome-gate rejection — not uncertain.
            throw $exception;
        } catch (AirBlueUnavailableException|AirBlueXmlException $exception) {
            if ($transmitted) {
                $this->persistSeatModifyUncertain($booking, $expected);

                throw new AirBlueValidationException(
                    $exception->normalizedCode !== '' ? $exception->normalizedCode : 'seat_modify_outcome_uncertain',
                    $exception->httpStatus,
                    'Seat modify mutation is uncertain; reconcile with Read only — do not retry Type=5.',
                    $exception->context,
                    $exception,
                );
            }

            throw new AirBlueValidationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                $exception->safeMessage,
                $exception->context,
                $exception,
            );
        } catch (AirBlueException $exception) {
            $code = (string) $exception->normalizedCode;
            if (AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission($transmitted, $code)) {
                $this->persistSeatModifyUncertain($booking, $expected);

                throw new AirBlueValidationException(
                    $code,
                    $exception->httpStatus,
                    'Seat modify mutation is uncertain; reconcile with Read only — do not retry Type=5.',
                    $exception->context,
                    $exception,
                );
            }

            throw new AirBlueValidationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                $exception->safeMessage,
                $exception->context,
                $exception,
            );
        } catch (\Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.seat_modify.unexpected', [
                'booking_id' => $booking?->id,
                'exception' => $exception::class,
                'transmitted' => $transmitted,
            ]);

            if ($transmitted) {
                $this->persistSeatModifyUncertain($booking, $expected);

                throw new AirBlueValidationException(
                    'seat_modify_unexpected',
                    500,
                    'Seat modify mutation is uncertain; reconcile with Read only — do not retry Type=5.',
                    ['booking_id' => $booking?->id],
                    $exception,
                );
            }

            throw $exception;
        }
    }

    public function logUnavailable(SupplierConnection $connection, string $operation): void
    {
        Log::channel('air-blue')->info('airblue.ancillary.probe', [
            'operation' => $operation,
            'api_channel' => 'zapways_ota',
            'protocol_version' => AirBlueZapwaysProtocolVersion::fromCredentials(
                is_array($connection->credentials) ? $connection->credentials : [],
            )->value,
            'supported' => $this->isSupported($connection),
        ]);
    }

    /**
     * @param  array<string, mixed>  $modifyContext
     */
    private function resolveBooking(array $modifyContext): ?Booking
    {
        $booking = $modifyContext['booking'] ?? null;
        if ($booking instanceof Booking) {
            return $booking;
        }

        $bookingId = (int) ($modifyContext['booking_id'] ?? 0);
        if ($bookingId > 0) {
            return Booking::query()->find($bookingId);
        }

        return null;
    }

    private function isSeatModifyUncertain(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        return ($context['seat_modify_outcome'] ?? '') === 'uncertain';
    }

    /**
     * @param  array<string, mixed>  $modifyContext
     * @return array{row_number: string, seat_number: string, traveler_ref_number_rph: string, flight_ref_number_rph: string}|null
     */
    private function expectedSeatFromContext(array $modifyContext): ?array
    {
        $changes = is_array($modifyContext['seat_changes'] ?? null) ? $modifyContext['seat_changes'] : [];
        if ($changes === [] || ! is_array($changes[0] ?? null)) {
            return null;
        }

        $change = $changes[0];

        return [
            'row_number' => trim((string) ($change['row_number'] ?? '')),
            'seat_number' => trim((string) ($change['seat_number'] ?? '')),
            'traveler_ref_number_rph' => trim((string) ($change['traveler_ref_number_rph'] ?? '')),
            'flight_ref_number_rph' => trim((string) ($change['flight_ref_number_rph'] ?? '')),
        ];
    }

    /**
     * @param  array{row_number: string, seat_number: string, traveler_ref_number_rph: string, flight_ref_number_rph: string}|null  $expected
     */
    private function persistSeatModifyUncertain(?Booking $booking, ?array $expected): void
    {
        if ($booking === null) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $context['seat_modify_outcome'] = 'uncertain';
        $context['seat_modify_requires_read'] = true;
        if ($expected !== null
            && $expected['row_number'] !== ''
            && $expected['seat_number'] !== '') {
            $context['seat_modify_expected'] = [
                'row_number' => $expected['row_number'],
                'seat_number' => $expected['seat_number'],
                'traveler_ref_number_rph' => $expected['traveler_ref_number_rph'],
                'flight_ref_number_rph' => $expected['flight_ref_number_rph'],
            ];
        }
        $meta['airblue_context'] = $context;
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * @param  array{row_number: string, seat_number: string, traveler_ref_number_rph: string, flight_ref_number_rph: string}|null  $expected
     */
    private function persistSeatModifySuccess(Booking $booking, ?array $expected): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $context['seat_modify_outcome'] = 'applied';
        $context['seat_modify_requires_read'] = false;
        unset($context['seat_modify_expected']);
        if ($expected !== null) {
            $context['seat_modify_last_applied'] = [
                'row_number' => $expected['row_number'],
                'seat_number' => $expected['seat_number'],
                'traveler_ref_number_rph' => $expected['traveler_ref_number_rph'],
                'flight_ref_number_rph' => $expected['flight_ref_number_rph'],
            ];
        }
        $meta['airblue_context'] = $context;
        $booking->meta = $meta;
        $booking->save();
    }
}
