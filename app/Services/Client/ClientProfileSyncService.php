<?php

namespace App\Services\Client;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\ClientProfile;
use App\Models\SupplierConnection;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Syncs the current config/ota_client.php deployment profile into client_profiles DB rows.
 */
final class ClientProfileSyncService
{
    public function __construct(
        private readonly ClientProfileConfigReader $configReader,
    ) {}

    /**
     * @return array{slug: string, created: bool, profile_id: int|null}
     */
    public function sync(?string $slug = null, bool $dryRun = false): array
    {
        $payload = $this->configReader->profilePayloadFromConfig($slug, mergeAgencyBranding: true);
        $slug = $payload['slug'];
        $branding = $payload['branding'];
        if (! is_array($branding)) {
            $branding = [];
        }

        if ($dryRun) {
            return [
                'slug' => $slug,
                'created' => ! ClientProfile::query()->where('slug', $slug)->exists(),
                'profile_id' => null,
            ];
        }

        return DB::transaction(function () use ($payload, $slug, $branding): array {
            $existing = ClientProfile::query()->where('slug', $slug)->first();
            $created = $existing === null;

            $profile = ClientProfile::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $payload['name'],
                    'domain' => $payload['domain'],
                    'environment' => $payload['environment'],
                    'active_frontend_theme' => $payload['theme'],
                    'asset_profile' => $payload['asset_profile'],
                    'default_locale' => $payload['default_locale'],
                    'timezone' => $payload['timezone'],
                    'currency' => $payload['currency'],
                    'is_master_profile' => (bool) $payload['is_master_profile'],
                    'is_active' => true,
                ],
            );

            $profile->branding()->updateOrCreate(
                ['client_profile_id' => $profile->id],
                [
                    'company_name' => (string) ($branding['company_name'] ?? $payload['name']),
                    'logo_path' => $branding['logo_path'] ?? null,
                    'favicon_path' => $branding['favicon_path'] ?? null,
                    'primary_color' => $branding['primary_color'] ?? null,
                    'secondary_color' => $branding['secondary_color'] ?? null,
                    'accent_color' => $branding['accent_color'] ?? null,
                    'phone' => $branding['phone'] ?? null,
                    'email' => $branding['email'] ?? null,
                    'address' => $branding['address'] ?? null,
                    'footer_text' => $branding['footer_text'] ?? null,
                ],
            );

            $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
            foreach ($this->configReader->normalizeModules($modules) as $moduleKey => $enabled) {
                $profile->modules()->updateOrCreate(
                    ['module_key' => $moduleKey],
                    ['enabled' => $enabled],
                );
            }

            $this->syncSuppliers($profile);
            $this->normalizeLegacyClientSupplierKeys($profile);

            return [
                'slug' => $slug,
                'created' => $created,
                'profile_id' => $profile->id,
            ];
        });
    }

    private function syncSuppliers(ClientProfile $profile): void
    {
        $connectionsByProvider = $this->loadAgencySupplierConnections();
        $seededKeys = [];

        foreach ($connectionsByProvider as $providerKey => $connection) {
            $seededKeys[] = $providerKey;
            $profile->suppliers()->updateOrCreate(
                ['supplier_key' => $providerKey],
                [
                    'enabled' => (bool) $connection->is_active,
                    'mode' => $connection->environment?->value,
                    'credentials' => is_array($connection->credentials) ? $connection->credentials : null,
                    'config' => is_array($connection->settings) ? $connection->settings : null,
                ],
            );
        }

        foreach (SupplierProvider::cases() as $provider) {
            $key = $provider->value;
            if (in_array($key, $seededKeys, true)) {
                continue;
            }

            $profile->suppliers()->updateOrCreate(
                ['supplier_key' => $key],
                ['enabled' => false],
            );
        }
    }

    /**
     * @return array<string, SupplierConnection>
     */
    private function loadAgencySupplierConnections(): array
    {
        if (! Schema::hasTable('supplier_connections') || ! Schema::hasTable('agencies')) {
            return [];
        }

        $agencySlug = trim((string) config('ota.default_agency_slug', ''));
        if ($agencySlug === '') {
            return [];
        }

        $agency = Agency::query()->where('slug', $agencySlug)->first();
        if ($agency === null) {
            return [];
        }

        $connections = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        $byProvider = [];
        foreach ($connections as $connection) {
            $key = $connection->provider->value;
            if (! array_key_exists($key, $byProvider)) {
                $byProvider[$key] = $connection;
            }
        }

        return $byProvider;
    }

    /**
     * Legacy Dev CP rows used supplier_key=pia before PiaNdc enum; map to pia_ndc and remove stale key.
     */
    private function normalizeLegacyClientSupplierKeys(ClientProfile $profile): void
    {
        $legacy = $profile->suppliers()->where('supplier_key', 'pia')->first();
        if ($legacy === null) {
            return;
        }

        $ndcKey = SupplierProvider::PiaNdc->value;
        $existingNdc = $profile->suppliers()->where('supplier_key', $ndcKey)->first();

        if ($existingNdc === null) {
            $profile->suppliers()->create([
                'supplier_key' => $ndcKey,
                'enabled' => (bool) $legacy->enabled,
                'mode' => $legacy->mode,
                'credentials' => $legacy->credentials,
                'config' => $legacy->config,
            ]);
        } elseif (! $existingNdc->enabled && $legacy->enabled) {
            $existingNdc->forceFill(['enabled' => true])->save();
        }

        $legacy->delete();
    }
}
