<?php

namespace App\Services\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\ClientProfileSupplier;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves client deployment profiles from DB with config/ota_client.php fallback helpers.
 *
 * Not wired into runtime routes or PlatformModuleGate in MC-2.
 */
final class ClientProfileResolver
{
    public function __construct(
        private readonly ClientProfileConfigReader $configReader,
    ) {}

    public function resolveBySlug(string $slug): ?ClientProfile
    {
        $slug = trim($slug);
        if ($slug === '' || ! Schema::hasTable('client_profiles')) {
            return null;
        }

        return ClientProfile::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['modules', 'suppliers', 'branding'])
            ->first();
    }

    public function defaultDeploymentSlug(): string
    {
        $canonical = trim((string) config('client.canonical_client.slug', ''));
        if ($canonical !== '') {
            return $canonical;
        }

        $slug = trim((string) config('ota_client.slug', ''));

        return $slug !== '' ? $slug : 'jetpk';
    }

    public function isDefaultDeploymentSlug(string $slug): bool
    {
        return strcasecmp(trim($slug), $this->defaultDeploymentSlug()) === 0;
    }

    public function resolveDefault(): ?ClientProfile
    {
        return $this->resolveBySlug($this->defaultDeploymentSlug());
    }

    /**
     * @return array<string, bool>
     */
    public function modulesFor(ClientProfile $profile): array
    {
        $modules = [];
        foreach ($profile->modules as $module) {
            if ($module instanceof ClientProfileModule) {
                $modules[$module->module_key] = (bool) $module->enabled;
            }
        }

        return $this->configReader->normalizeModules($modules);
    }

    /**
     * @return Collection<int, ClientProfileSupplier>
     */
    public function suppliersFor(ClientProfile $profile): Collection
    {
        return $profile->suppliers()->orderBy('supplier_key')->get();
    }

    public function brandingFor(ClientProfile $profile): ?ClientProfileBranding
    {
        return $profile->branding;
    }

    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     theme: string,
     *     asset_profile: string,
     *     environment: string,
     *     default_locale: string,
     *     timezone: string,
     *     currency: string,
     *     domain: string,
     *     modules: array<string, bool>,
     *     branding: array<string, mixed>,
     *     suppliers: list<array{supplier_key: string, enabled: bool, mode: ?string, config: ?array<string, mixed>}>
     * }
     */
    public function toRuntimeConfig(ClientProfile $profile): array
    {
        $branding = $profile->branding;
        $brandingData = [
            'company_name' => $branding?->company_name ?? $profile->name,
            'domain' => $profile->domain ?? '',
            'phone' => $branding?->phone ?? '',
            'email' => $branding?->email ?? '',
            'address' => $branding?->address ?? '',
            'footer_text' => $branding?->footer_text ?? '',
            'primary_color' => $branding?->primary_color ?? '#0c4a6e',
            'secondary_color' => $branding?->secondary_color ?? '#0ea5e9',
            'accent_color' => $branding?->accent_color ?? '#f59e0b',
            'logo_path' => $branding?->logo_path ?? 'logo/logo.svg',
            'favicon_path' => $branding?->favicon_path ?? 'favicon/favicon.ico',
            'timezone' => $profile->timezone,
            'currency' => $profile->currency,
            'storage_logo_path' => null,
            'storage_favicon_path' => null,
            'storage_hero_image_path' => null,
        ];

        $suppliers = [];
        foreach ($this->suppliersFor($profile) as $supplier) {
            $suppliers[] = [
                'supplier_key' => $supplier->supplier_key,
                'enabled' => (bool) $supplier->enabled,
                'mode' => $supplier->mode,
                'config' => is_array($supplier->config) ? $supplier->config : null,
            ];
        }

        return [
            'slug' => $profile->slug,
            'name' => $profile->name,
            'theme' => $profile->active_frontend_theme,
            'asset_profile' => $profile->asset_profile,
            'environment' => $profile->environment,
            'default_locale' => $profile->default_locale,
            'timezone' => $profile->timezone,
            'currency' => $profile->currency,
            'domain' => $profile->domain ?? $brandingData['domain'],
            'modules' => $this->modulesFor($profile),
            'branding' => $brandingData,
            'suppliers' => $suppliers,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function modulesFromConfig(): array
    {
        return $this->configReader->modulesFromConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function brandingFromConfig(): array
    {
        return $this->configReader->brandingFromConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayloadFromConfig(?string $slug = null): array
    {
        return $this->configReader->profilePayloadFromConfig($slug, mergeAgencyBranding: false);
    }
}
