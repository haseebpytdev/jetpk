<?php

namespace App\Support\Suppliers;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Sabre GDS vs NDC lane gates on a single shared SupplierConnection (auth/credentials).
 *
 * Admin connection settings control which result lanes are active. Env global_kill_switch
 * flags are explicit deployment kill switches only — not lane defaults.
 */
final class SabreChannelGateResolver
{
    public function __construct(
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
        private readonly SabreClient $sabreClient,
    ) {}

    public function globalNdcKillSwitch(): bool
    {
        $ndc = config('suppliers.sabre.ndc', []);

        return is_array($ndc) && (bool) ($ndc['global_kill_switch'] ?? false);
    }

    public function globalGdsKillSwitch(): bool
    {
        return (bool) config('suppliers.sabre.gds_global_kill_switch', false);
    }

    public function connectionNdcEnabled(SupplierConnection $connection): bool
    {
        return SabreSupplierChannelConfig::ndcEnabled($connection);
    }

    public function connectionGdsEnabled(SupplierConnection $connection): bool
    {
        return SabreSupplierChannelConfig::gdsEnabled($connection);
    }

    public function effectiveNdcEnabled(SupplierConnection $connection): bool
    {
        return $this->platformModuleEnforcer->sabreEffectiveNdcEnabled($connection);
    }

    public function effectiveGdsEnabled(SupplierConnection $connection): bool
    {
        return $this->platformModuleEnforcer->sabreEffectiveGdsEnabled($connection);
    }

    /**
     * @return list<string> Values: gds, ndc
     */
    public function selectedSabreLanes(SupplierConnection $connection): array
    {
        $lanes = [];
        if ($this->effectiveGdsEnabled($connection)) {
            $lanes[] = 'gds';
        }
        if ($this->effectiveNdcEnabled($connection)) {
            $lanes[] = 'ndc';
        }

        return $lanes;
    }

    public function sabreConnectionSearchEnabled(SupplierConnection $connection): bool
    {
        return $this->selectedSabreLanes($connection) !== [];
    }

    public function sharedCredentialsPresent(?SupplierConnection $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        try {
            return $this->sabreClient->connectionHasTokenCredentials($connection);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Read-only lane + credential diagnostics (no HTTP).
     *
     * @return array<string, mixed>
     */
    public function diagnostics(?SupplierConnection $connection): array
    {
        $connectionNdc = $connection !== null && $this->connectionNdcEnabled($connection);
        $connectionGds = $connection !== null && $this->connectionGdsEnabled($connection);
        $effectiveNdc = $connection !== null && $this->effectiveNdcEnabled($connection);
        $effectiveGds = $connection !== null && $this->effectiveGdsEnabled($connection);
        $selectedLanes = $connection !== null ? $this->selectedSabreLanes($connection) : [];
        $credentialsPresent = $this->sharedCredentialsPresent($connection);

        return [
            'active_connection_id' => $connection?->id,
            'selected_sabre_lanes' => $selectedLanes,
            'connection_ndc_channel_enabled' => $connectionNdc,
            'connection_gds_channel_enabled' => $connectionGds,
            'global_ndc_kill_switch' => $this->globalNdcKillSwitch(),
            'global_gds_kill_switch' => $this->globalGdsKillSwitch(),
            'sabre_ndc_module_enabled' => $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_ndc'),
            'sabre_gds_module_enabled' => $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_gds'),
            'effective_ndc_enabled' => $effectiveNdc,
            'effective_gds_enabled' => $effectiveGds,
            'gds_results_suppressed' => $connection !== null && ! $effectiveGds,
            'ndc_results_allowed' => $effectiveNdc,
            'gds_suppressed' => $connection !== null && ! $effectiveGds,
            'ndc_allowed' => $effectiveNdc,
            'credentials_shared' => true,
            'shared_credentials_present' => $credentialsPresent,
            'credentials_source' => 'supplier_connection_shared_with_gds',
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => false,
        ];
    }

    /**
     * @return list<string>
     */
    public function ndcLaneBlockers(?SupplierConnection $connection): array
    {
        $blockers = [];

        if ($connection === null) {
            $blockers[] = 'no_supplier_connection';

            return $blockers;
        }

        if ($this->globalNdcKillSwitch()) {
            $blockers[] = 'global_ndc_kill_switch_active';
        }

        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_ndc')) {
            $blockers[] = 'sabre_ndc_module_disabled';
        }

        if (! $this->connectionNdcEnabled($connection)) {
            $blockers[] = 'connection_ndc_channel_disabled';
        }

        if (! $this->sharedCredentialsPresent($connection)) {
            $blockers[] = 'credentials_missing';
        }

        return array_values(array_unique($blockers));
    }

    public function ndcProviderIncluded(SupplierConnection $connection): bool
    {
        return in_array('ndc', $this->selectedSabreLanes($connection), true);
    }

    public function gdsProviderIncluded(SupplierConnection $connection): bool
    {
        return in_array('gds', $this->selectedSabreLanes($connection), true);
    }

    public function ndcLaneExclusionReason(SupplierConnection $connection): ?string
    {
        if ($this->ndcProviderIncluded($connection)) {
            return null;
        }

        if ($this->globalNdcKillSwitch()) {
            return 'global_ndc_kill_switch_active';
        }

        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_ndc')) {
            return 'sabre_ndc_module_disabled';
        }

        if (! $this->connectionNdcEnabled($connection)) {
            return 'connection_ndc_channel_disabled';
        }

        if (! $this->sharedCredentialsPresent($connection)) {
            return 'credentials_missing';
        }

        return 'ndc_lane_not_selected';
    }

    public function gdsLaneExclusionReason(SupplierConnection $connection): ?string
    {
        if ($this->gdsProviderIncluded($connection)) {
            return null;
        }

        if ($this->globalGdsKillSwitch()) {
            return 'global_gds_kill_switch_active';
        }

        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_gds')) {
            return 'sabre_gds_module_disabled';
        }

        if (! $this->connectionGdsEnabled($connection)) {
            return 'connection_gds_channel_disabled';
        }

        return 'gds_lane_not_selected';
    }
}
