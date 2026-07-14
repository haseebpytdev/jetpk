<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Canonical supplier + channel + fare context for booking lifecycle routing (no live supplier calls).
 */
final class SupplierLifecycleContextResolver
{
    public const HANDLER_SABRE_GDS = 'sabre_gds';

    public const HANDLER_SABRE_NDC = 'sabre_ndc';

    public const HANDLER_PIA_NDC = 'pia_ndc';

    public const HANDLER_AIRBLUE = 'airblue';

    public const HANDLER_AIRSIAL = 'airsial';

    public const HANDLER_DUFFEL = 'duffel';

    public const HANDLER_GROUP = 'group';

    public const HANDLER_IATI = 'iati';

    public const HANDLER_OTHER = 'other';

    public const CHANNEL_GDS = 'gds';

    public const CHANNEL_NDC = 'ndc';

    public const CHANNEL_DIRECT = 'direct';

    public const CHANNEL_GROUP = 'group';

    public const CHANNEL_OTHER = 'other';

    public function __construct(
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @return array{
     *     supplier_provider: string,
     *     supplier_channel: string,
     *     selected_offer_source: string,
     *     selected_fare_context: array<string, mixed>,
     *     handler_key: string,
     *     display_label: string,
     *     supports_revalidation: bool,
     *     supports_pnr_or_order: bool,
     *     supports_ticketing: bool,
     *     supports_cancellation: bool,
     *     supports_void: bool,
     *     supports_refund: bool
     * }
     */
    public function resolve(Booking $booking): array
    {
        $meta = $this->meta($booking);
        $provider = $this->resolveProvider($booking, $meta);
        $channel = $this->resolveChannel($booking, $meta, $provider);
        $handlerKey = $this->resolveHandlerKey($provider, $meta, $channel);
        $capabilities = $this->capabilitiesForHandler($handlerKey);

        return [
            'supplier_provider' => $provider,
            'supplier_channel' => $channel,
            'selected_offer_source' => $this->resolveSelectedOfferSource($meta),
            'selected_fare_context' => $this->resolveSelectedFareContext($meta, $provider),
            'handler_key' => $handlerKey,
            'display_label' => $this->displayLabelForHandler($handlerKey),
            ...$capabilities,
        ];
    }

    public function isHandler(Booking $booking, string $handlerKey): bool
    {
        return $this->resolve($booking)['handler_key'] === $handlerKey;
    }

    public function handlerKey(Booking $booking): string
    {
        return $this->resolve($booking)['handler_key'];
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(Booking $booking): array
    {
        $meta = $booking->meta;

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveProvider(Booking $booking, array $meta): string
    {
        $candidates = [
            strtolower(trim((string) ($booking->supplier ?? ''))),
            strtolower(trim((string) ($meta['supplier_provider'] ?? ''))),
            strtolower(trim((string) data_get($meta, 'flight_offer_snapshot.supplier_provider', ''))),
            strtolower(trim((string) data_get($meta, 'normalized_offer_snapshot.supplier_provider', ''))),
            strtolower(trim((string) data_get($meta, 'validated_offer_snapshot.supplier_provider', ''))),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $sourceChannel = strtolower(trim((string) ($booking->source_channel ?? $meta['source_channel'] ?? $meta['origin_channel'] ?? '')));
        if (str_contains($sourceChannel, 'group')) {
            return 'group';
        }

        if ((int) ($booking->supplier_connection_id ?? $meta['supplier_connection_id'] ?? 0) > 0) {
            return strtolower(trim((string) ($meta['supplier_provider'] ?? self::HANDLER_OTHER)));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveChannel(Booking $booking, array $meta, string $provider): string
    {
        $candidates = [
            strtolower(trim((string) data_get($meta, 'sabre_booking_context.distribution_channel', ''))),
            strtolower(trim((string) ($meta['selected_supplier_channel'] ?? ''))),
            strtolower(trim((string) ($meta['distribution_channel'] ?? ''))),
            strtolower(trim((string) data_get($meta, 'flight_offer_snapshot.distribution_channel', ''))),
            strtolower(trim((string) data_get($meta, 'normalized_offer_snapshot.distribution_channel', ''))),
            strtolower(trim((string) data_get($meta, 'validated_offer_snapshot.distribution_channel', ''))),
            strtolower(trim((string) data_get($meta, 'flight_offer_snapshot.raw_payload.distribution_model', ''))),
            strtolower(trim((string) data_get($meta, 'normalized_offer_snapshot.raw_payload.distribution_model', ''))),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (in_array($candidate, ['gds', 'ndc', 'direct', 'group'], true)) {
                return $candidate;
            }
            if ($candidate === 'ndc_offer' || str_contains($candidate, 'ndc')) {
                return self::CHANNEL_NDC;
            }
        }

        if ($provider === SupplierProvider::Sabre->value) {
            $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);

            return $this->platformModuleEnforcer->isSabreNdcDistributionChannel($distributionChannel)
                ? self::CHANNEL_NDC
                : self::CHANNEL_GDS;
        }

        if (in_array($provider, [SupplierProvider::PiaNdc->value], true)) {
            return self::CHANNEL_NDC;
        }

        if (in_array($provider, ['group', 'al_haider', 'group_ticketing'], true)) {
            return self::CHANNEL_GROUP;
        }

        return self::CHANNEL_OTHER;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveHandlerKey(string $provider, array $meta, string $channel): string
    {
        if ($provider === SupplierProvider::Sabre->value) {
            return $channel === self::CHANNEL_NDC
                ? self::HANDLER_SABRE_NDC
                : self::HANDLER_SABRE_GDS;
        }

        return match ($provider) {
            SupplierProvider::PiaNdc->value => self::HANDLER_PIA_NDC,
            SupplierProvider::Airblue->value => self::HANDLER_AIRBLUE,
            SupplierProvider::Duffel->value => self::HANDLER_DUFFEL,
            SupplierProvider::Iati->value => self::HANDLER_IATI,
            'airsial', 'air_sial' => self::HANDLER_AIRSIAL,
            'group', 'al_haider', 'group_ticketing' => self::HANDLER_GROUP,
            default => $provider !== '' ? self::HANDLER_OTHER : self::HANDLER_OTHER,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveSelectedOfferSource(array $meta): string
    {
        foreach ([
            trim((string) ($meta['offer_source'] ?? '')),
            trim((string) data_get($meta, 'normalized_offer_snapshot.source', '')),
            trim((string) data_get($meta, 'flight_offer_snapshot.source', '')),
            trim((string) data_get($meta, 'normalized_offer_snapshot.id', '')),
            trim((string) data_get($meta, 'flight_offer_snapshot.id', '')),
            trim((string) ($meta['selected_offer_id'] ?? '')),
        ] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (is_array($meta['normalized_offer_snapshot'] ?? null) && $meta['normalized_offer_snapshot'] !== []) {
            return 'normalized_offer_snapshot';
        }
        if (is_array($meta['flight_offer_snapshot'] ?? null) && $meta['flight_offer_snapshot'] !== []) {
            return 'flight_offer_snapshot';
        }
        if ((int) ($meta['supplier_connection_id'] ?? 0) > 0) {
            return 'supplier_connection_fallback';
        }

        return 'unknown';
    }

    /**
     * Authoritative selected fare — never infer from cheapest/default snapshot alone.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolveSelectedFareContext(array $meta, string $provider): array
    {
        $selected = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : [];
        $fareOptionKey = trim((string) ($meta['fare_option_key'] ?? ''));
        $selectedKey = trim((string) ($selected['option_key'] ?? $selected['fare_option_key'] ?? ''));

        $context = [
            'fare_option_key' => $fareOptionKey !== '' ? $fareOptionKey : ($selectedKey !== '' ? $selectedKey : null),
            'brand_code' => $selected['brand_code'] ?? data_get($meta, 'selected_brand_code'),
            'brand_name' => $selected['brand_name'] ?? ($selected['name'] ?? null) ?? data_get($meta, 'selected_brand_name'),
            'booking_class' => $selected['booking_class'] ?? null,
            'fare_basis' => $selected['fare_basis'] ?? null,
            'selection_source' => match (true) {
                $fareOptionKey !== '' => 'fare_option_key',
                $selected !== [] => 'selected_fare_family_option',
                default => 'none',
            },
        ];

        if ($provider === SupplierProvider::Sabre->value) {
            $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
            if ($context['brand_code'] === null) {
                $context['brand_code'] = $handoff['brand_code'] ?? $handoff['selected_brand_code'] ?? null;
            }
        }

        if ($provider === SupplierProvider::PiaNdc->value) {
            $piaContext = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
            if ($context['brand_code'] === null) {
                $context['brand_code'] = $piaContext['brand_code'] ?? data_get($meta, 'pia_ndc_selected_fare.brand_code');
            }
        }

        return array_filter($context, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array{
     *     supports_revalidation: bool,
     *     supports_pnr_or_order: bool,
     *     supports_ticketing: bool,
     *     supports_cancellation: bool,
     *     supports_void: bool,
     *     supports_refund: bool
     * }
     */
    protected function capabilitiesForHandler(string $handlerKey): array
    {
        return match ($handlerKey) {
            self::HANDLER_SABRE_GDS => [
                'supports_revalidation' => true,
                'supports_pnr_or_order' => true,
                'supports_ticketing' => true,
                'supports_cancellation' => true,
                'supports_void' => true,
                'supports_refund' => true,
            ],
            self::HANDLER_SABRE_NDC => [
                'supports_revalidation' => true,
                'supports_pnr_or_order' => true,
                'supports_ticketing' => false,
                'supports_cancellation' => false,
                'supports_void' => false,
                'supports_refund' => false,
            ],
            self::HANDLER_PIA_NDC => [
                'supports_revalidation' => true,
                'supports_pnr_or_order' => true,
                'supports_ticketing' => true,
                'supports_cancellation' => true,
                'supports_void' => true,
                'supports_refund' => false,
            ],
            self::HANDLER_AIRBLUE, self::HANDLER_AIRSIAL => [
                'supports_revalidation' => true,
                'supports_pnr_or_order' => true,
                'supports_ticketing' => true,
                'supports_cancellation' => true,
                'supports_void' => false,
                'supports_refund' => false,
            ],
            self::HANDLER_DUFFEL => [
                'supports_revalidation' => true,
                'supports_pnr_or_order' => true,
                'supports_ticketing' => true,
                'supports_cancellation' => true,
                'supports_void' => false,
                'supports_refund' => false,
            ],
            self::HANDLER_IATI => [
                'supports_revalidation' => false,
                'supports_pnr_or_order' => true,
                'supports_ticketing' => false,
                'supports_cancellation' => true,
                'supports_void' => false,
                'supports_refund' => false,
            ],
            self::HANDLER_GROUP => [
                'supports_revalidation' => false,
                'supports_pnr_or_order' => false,
                'supports_ticketing' => false,
                'supports_cancellation' => false,
                'supports_void' => false,
                'supports_refund' => false,
            ],
            default => [
                'supports_revalidation' => false,
                'supports_pnr_or_order' => false,
                'supports_ticketing' => false,
                'supports_cancellation' => false,
                'supports_void' => false,
                'supports_refund' => false,
            ],
        };
    }

    protected function displayLabelForHandler(string $handlerKey): string
    {
        return match ($handlerKey) {
            self::HANDLER_SABRE_GDS => 'Sabre GDS',
            self::HANDLER_SABRE_NDC => 'Sabre NDC',
            self::HANDLER_PIA_NDC => 'PIA NDC',
            self::HANDLER_AIRBLUE => 'AirBlue',
            self::HANDLER_AIRSIAL => 'AirSial',
            self::HANDLER_DUFFEL => 'Duffel',
            self::HANDLER_GROUP => 'Group fare',
            self::HANDLER_IATI => 'IATI',
            default => 'Other supplier',
        };
    }
}
