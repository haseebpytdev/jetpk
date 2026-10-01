<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
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
}
