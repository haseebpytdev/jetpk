<?php

namespace App\Support\Platform;

use App\Enums\SupplierProvider;
use App\Exceptions\PlatformModuleDisabledException;
use App\Models\SupplierConnection;
use App\Services\Platform\CompanyModuleEntitlementService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Illuminate\Support\Facades\Auth;

/**
 * Central backend platform module enforcement (Sprint 8L+; payment/wallet 8M; search/checkout 8N; supplier booking/ticketing 8O; provider polish 8P).
 */
final class PlatformModuleEnforcer
{
    public function __construct(
        private readonly PlatformModuleSettingsService $settings,
        private readonly CompanyModuleEntitlementService $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function ensureModuleEnabled(string $key, ?string $action = null, array $context = []): void
    {
        if ($this->effectiveModuleEnabled($key)) {
            return;
        }

        throw new PlatformModuleDisabledException($key, $action, $context);
    }

    public function ensureSupplierSearchEnabled(?string $sourceChannel = null, ?string $provider = null, ?string $distributionChannel = null): void
    {
        $context = array_filter([
            'source_channel' => $sourceChannel,
            'provider' => $provider,
            'distribution_channel' => $distributionChannel,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $this->ensureModuleEnabled('supplier_search', 'supplier_search', $context);

        if ($provider !== null && trim($provider) !== '' && ! $this->providerChannelEnabled($provider, $distributionChannel)) {
            throw new PlatformModuleDisabledException(
                $this->resolveProviderModuleKey($provider, $distributionChannel) ?? 'supplier_search',
                'supplier_search',
                $context,
            );
        }
    }

    public function ensureSupplierBookingEnabled(?string $provider = null, bool $allowManualOverride = false, ?string $distributionChannel = null): void
    {
        $context = array_filter([
            'provider' => $provider,
            'distribution_channel' => $distributionChannel,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $this->ensureModuleEnabled('supplier_booking', 'supplier_booking', $context);

        if ($allowManualOverride) {
            return;
        }

        if ($provider !== null && trim($provider) !== '' && ! $this->providerChannelEnabled($provider, $distributionChannel)) {
            throw new PlatformModuleDisabledException(
                $this->resolveProviderModuleKey($provider, $distributionChannel) ?? 'supplier_booking',
                'supplier_booking',
                $context,
            );
        }
    }

    public function ensureTicketingEnabled(?string $provider = null, ?string $distributionChannel = null): void
    {
        $context = array_filter([
            'provider' => $provider,
            'distribution_channel' => $distributionChannel,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $this->ensureModuleEnabled('ticketing', 'ticketing', $context);
        $this->ensureModuleEnabled('supplier_booking', 'ticketing', $context);

        if ($provider !== null && trim($provider) !== '' && ! $this->providerChannelEnabled($provider, $distributionChannel)) {
            throw new PlatformModuleDisabledException(
                $this->resolveProviderModuleKey($provider, $distributionChannel) ?? 'ticketing',
                'ticketing',
                $context,
            );
        }
    }

    public function ensurePaymentProofsEnabled(): void
    {
        $this->ensureModuleEnabled('payment_proofs', 'payment_proofs');
    }

    public function ensureAgentWalletEnabled(): void
    {
        $this->ensureModuleEnabled('agent_wallet', 'agent_wallet');
    }

    public function ensureAgentDepositsEnabled(): void
    {
        $this->ensureModuleEnabled('agent_deposits', 'agent_deposits');
    }

    public function ensurePublicFlightSearchEnabled(): void
    {
        $this->ensureModuleEnabled('public_flight_search', 'public_flight_search');
    }

    public function ensureCustomerCheckoutEnabled(): void
    {
        $this->ensureModuleEnabled('customer_checkout', 'customer_checkout');
    }

    public function supplierBookingBlockedMessage(
        ?string $provider = null,
        bool $allowManualOverride = false,
        ?string $distributionChannel = null,
    ): ?string {
        try {
            $this->ensureSupplierBookingEnabled($provider, $allowManualOverride, $distributionChannel);
        } catch (PlatformModuleDisabledException) {
            return PlatformModuleDisabledException::PUBLIC_MESSAGE;
        }

        return null;
    }

    public function ticketingBlockedMessage(?string $provider = null, ?string $distributionChannel = null): ?string
    {
        try {
            $this->ensureTicketingEnabled($provider, $distributionChannel);
        } catch (PlatformModuleDisabledException) {
            return PlatformModuleDisabledException::PUBLIC_MESSAGE;
        }

        return null;
    }

    public function sabreSearchEnabled(): bool
    {
        return $this->effectiveModuleEnabled('sabre_gds') || $this->effectiveModuleEnabled('sabre_ndc');
    }

    public function sabreConnectionSearchEnabled(SupplierConnection $connection): bool
    {
        if ($connection->provider !== SupplierProvider::Sabre || ! $connection->isActive()) {
            return false;
        }

        return $this->sabreEffectiveGdsEnabled($connection) || $this->sabreEffectiveNdcEnabled($connection);
    }

    public function sabreConnectionAllowsChannel(SupplierConnection $connection, ?string $distributionChannel = null): bool
    {
        if ($connection->provider !== SupplierProvider::Sabre || ! $connection->isActive()) {
            return false;
        }

        if ($this->isSabreNdcDistributionChannel($distributionChannel)) {
            return $this->sabreEffectiveNdcEnabled($connection);
        }

        return $this->sabreEffectiveGdsEnabled($connection);
    }

    public function sabreEffectiveNdcEnabled(SupplierConnection $connection): bool
    {
        if ($connection->provider !== SupplierProvider::Sabre || ! $connection->isActive()) {
            return false;
        }

        $ndc = config('suppliers.sabre.ndc', []);

        return SabreSupplierChannelConfig::ndcEnabled($connection)
            && $this->effectiveModuleEnabled('sabre_ndc')
            && ! (is_array($ndc) && (bool) ($ndc['global_kill_switch'] ?? false));
    }

    public function sabreEffectiveGdsEnabled(SupplierConnection $connection): bool
    {
        if ($connection->provider !== SupplierProvider::Sabre || ! $connection->isActive()) {
            return false;
        }

        return SabreSupplierChannelConfig::gdsEnabled($connection)
            && $this->effectiveModuleEnabled('sabre_gds')
            && ! (bool) config('suppliers.sabre.gds_global_kill_switch', false);
    }

    public function isSabreNdcDistributionChannel(?string $distributionChannel): bool
    {
        $channel = strtolower(trim((string) $distributionChannel));

        return $channel !== '' && str_contains($channel, 'ndc');
    }

    public function providerChannelEnabled(string $provider, ?string $distributionChannel = null): bool
    {
        $normalized = strtolower(str_replace('-', '_', trim($provider)));

        if (in_array($normalized, ['sabre', 'sabre_gds', 'sabre_ndc', 'gds', 'ndc'], true)) {
            if ($this->isSabreNdcDistributionChannel($distributionChannel)) {
                return $this->effectiveModuleEnabled('sabre_ndc');
            }

            return $this->effectiveModuleEnabled('sabre_gds');
        }

        $providerKey = $this->providerModuleKey($normalized);

        return $providerKey === null || $this->effectiveModuleEnabled($providerKey);
    }

    /**
     * Resolve Sabre GDS vs NDC (and similar) from booking meta without defaulting unknown channels to GDS.
     *
     * @param  array<string, mixed>  $meta
     */
    public function distributionChannelFromBookingMeta(array $meta): ?string
    {
        $candidates = [];

        if (isset($meta['distribution_channel'])) {
            $candidates[] = $meta['distribution_channel'];
        }

        foreach (['validated_offer_snapshot', 'flight_offer_snapshot', 'selected_offer_snapshot', 'normalized_offer_snapshot'] as $snapshotKey) {
            $snapshot = is_array($meta[$snapshotKey] ?? null) ? $meta[$snapshotKey] : [];
            if (isset($snapshot['distribution_channel'])) {
                $candidates[] = $snapshot['distribution_channel'];
            }
        }

        foreach (['provider_channel', 'sabre_distribution_channel'] as $aliasKey) {
            if (isset($meta[$aliasKey])) {
                $candidates[] = $meta[$aliasKey];
            }
        }

        $provider = strtolower(str_replace('-', '_', trim((string) ($meta['supplier_provider'] ?? ''))));
        if (in_array($provider, ['sabre_ndc', 'ndc'], true)) {
            $candidates[] = 'NDC';
        }

        foreach ($candidates as $channel) {
            if (! is_string($channel)) {
                continue;
            }
            $normalized = trim($channel);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        // Unknown channel for Sabre: return null. providerChannelEnabled() still treats null as GDS for Sabre.
        return null;
    }

    public function resolveProviderModuleKey(?string $provider, ?string $distributionChannel = null): ?string
    {
        if ($provider === null || trim($provider) === '') {
            return null;
        }

        $normalized = strtolower(str_replace('-', '_', trim($provider)));

        if (in_array($normalized, ['sabre', 'sabre_gds', 'sabre_ndc', 'gds', 'ndc'], true)) {
            return $this->isSabreNdcDistributionChannel($distributionChannel) ? 'sabre_ndc' : 'sabre_gds';
        }

        return $this->providerModuleKey($normalized);
    }

    public function routeEnabled(string $key): bool
    {
        return $this->effectiveModuleEnabled($key);
    }

    public function effectiveModuleEnabled(string $key, ?int $agencyId = null): bool
    {
        $module = PlatformModuleRegistry::find($key);

        if ($module === null) {
            return false;
        }

        if ($module->protected) {
            return true;
        }

        $globalEnabled = $this->settings->stateFor($key);
        $resolvedAgencyId = $agencyId ?? $this->resolveAgencyIdFromContext();

        if ($resolvedAgencyId === null) {
            return $globalEnabled;
        }

        return $this->entitlements->isModuleEnabledForAgency($resolvedAgencyId, $key, $globalEnabled);
    }

    public function effectiveModuleEnabledForAgency(string $key, int $agencyId): bool
    {
        return $this->effectiveModuleEnabled($key, $agencyId);
    }

    private function resolveAgencyIdFromContext(): ?int
    {
        $user = Auth::user();
        if ($user !== null && $user->current_agency_id !== null) {
            return (int) $user->current_agency_id;
        }

        return null;
    }

    public function providerModuleKey(?string $provider): ?string
    {
        if ($provider === null || trim($provider) === '') {
            return null;
        }

        $normalized = strtolower(str_replace('-', '_', trim($provider)));

        return match ($normalized) {
            'sabre', 'sabre_gds', 'gds' => 'sabre_gds',
            'sabre_ndc', 'ndc' => 'sabre_ndc',
            'duffel' => 'duffel_supplier',
            'iati' => 'iati_supplier',
            'pia_ndc' => 'pia_ndc_supplier',
            'airblue' => 'airblue_supplier',
            default => null,
        };
    }
}
