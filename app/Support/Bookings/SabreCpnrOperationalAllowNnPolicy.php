<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;

/**
 * BF7-J-OPS-FIX2: CERT-only operational allow-NN — omit NN/WN from HaltOnStatus on live Passenger Records create.
 * Stricter than {@code sabre:cert-gds-cpnr-report --allow-nn-cert-diagnostic} (PK/QR cert command only).
 */
final class SabreCpnrOperationalAllowNnPolicy
{
    public const POLICY_DEFAULT_IATI_WITH_NN = 'default_iati_with_nn';

    public const POLICY_CERT_OPERATIONAL_OMIT_NN_WN = 'cert_operational_omit_nn_wn';

    public static function isConfigEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', false);
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array{
     *     should_omit_nn_wn: bool,
     *     allow_nn_cert_operational: bool,
     *     halt_on_status_policy: string,
     *     halt_on_status_nn_omitted: bool,
     *     block_reason: ?string
     * }
     */
    public function evaluate(
        array $apiDraft,
        string $payloadStyle,
        string $endpointPath,
        ?SupplierConnection $connection,
        ?Booking $booking = null,
    ): array {
        $default = [
            'should_omit_nn_wn' => false,
            'allow_nn_cert_operational' => false,
            'halt_on_status_policy' => self::POLICY_DEFAULT_IATI_WITH_NN,
            'halt_on_status_nn_omitted' => false,
            'block_reason' => null,
        ];

        if (! self::isConfigEnabled()) {
            return array_merge($default, ['block_reason' => 'config_disabled']);
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            return array_merge($default, ['block_reason' => 'ticketing_enabled']);
        }

        if ($payloadStyle !== SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS) {
            return array_merge($default, ['block_reason' => 'requires_iati_v24_style']);
        }

        if ($endpointPath !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
            return array_merge($default, ['block_reason' => 'requires_v24_create_endpoint']);
        }

        if ($connection === null) {
            return array_merge($default, ['block_reason' => 'missing_supplier_connection']);
        }

        $baseUrl = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($baseUrl === '' || SabreInspectGate::isProductionLiveSabreHost($baseUrl)) {
            return array_merge($default, ['block_reason' => 'requires_cert_host']);
        }

        if (! SabreInspectGate::isCertSabreHost($baseUrl)) {
            return array_merge($default, ['block_reason' => 'requires_cert_host']);
        }

        if ($booking !== null) {
            if (trim((string) ($booking->pnr ?? '')) !== '') {
                return array_merge($default, ['block_reason' => 'pnr_already_exists']);
            }
            if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
                return array_merge($default, ['block_reason' => 'supplier_reference_already_exists']);
            }
        }

        $itineraryBlock = $this->resolveItineraryBlockReason($apiDraft);
        if ($itineraryBlock !== null) {
            return array_merge($default, ['block_reason' => $itineraryBlock]);
        }

        return [
            'should_omit_nn_wn' => true,
            'allow_nn_cert_operational' => true,
            'halt_on_status_policy' => self::POLICY_CERT_OPERATIONAL_OMIT_NN_WN,
            'halt_on_status_nn_omitted' => true,
            'block_reason' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     */
    public function shouldOmitNnWnFromHaltOnStatus(
        array $apiDraft,
        string $payloadStyle,
        string $endpointPath,
        ?SupplierConnection $connection,
        ?Booking $booking = null,
    ): bool {
        return ($this->evaluate($apiDraft, $payloadStyle, $endpointPath, $connection, $booking)['should_omit_nn_wn'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     */
    protected function resolveItineraryBlockReason(array $apiDraft): ?string
    {
        $segments = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
        if (count($segments) !== 2) {
            return 'requires_two_segments';
        }

        $carriers = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) (
                $seg['carrier']
                ?? $seg['airline_code']
                ?? $seg['marketing_carrier']
                ?? data_get($seg, 'marketing_airline.code')
                ?? ''
            )));
            if ($carrier !== '') {
                $carriers[$carrier] = true;
            }
        }

        if ($carriers === []) {
            return 'requires_same_carrier_connecting';
        }

        if (count($carriers) > 1) {
            return 'blocks_mixed_carrier';
        }

        return null;
    }
}
