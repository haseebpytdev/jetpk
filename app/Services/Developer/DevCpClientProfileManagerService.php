<?php

namespace App\Services\Developer;

use App\Enums\SupplierProvider;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileSupplier;
use App\Models\DeveloperUser;
use App\Services\Platform\PlatformAuditLogger;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dev CP client profile CRUD, tab updates, and duplicate operations (MC-3).
 */
class DevCpClientProfileManagerService
{
    public function __construct(
        protected ClientProfileConfigReader $configReader,
        protected PlatformAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     slug: string,
     *     domain?: ?string,
     *     environment?: string,
     *     default_locale?: string,
     *     timezone?: string,
     *     currency?: string,
     *     is_active?: bool,
     *     active_frontend_theme?: string,
     *     asset_profile?: string
     * }  $data
     */
    public function createProfile(array $data, DeveloperUser $developer, ?Request $request = null): ClientProfile
    {
        return DB::transaction(function () use ($data, $developer, $request): ClientProfile {
            $slug = Str::slug(trim($data['slug']));
            $assetProfile = trim((string) ($data['asset_profile'] ?? $slug));

            $profile = ClientProfile::query()->create([
                'name' => trim($data['name']),
                'slug' => $slug,
                'domain' => $this->nullableString($data['domain'] ?? null),
                'environment' => trim((string) ($data['environment'] ?? 'production')),
                'active_frontend_theme' => trim((string) ($data['active_frontend_theme'] ?? 'v1-classic')),
                'asset_profile' => $assetProfile !== '' ? $assetProfile : $slug,
                'default_locale' => trim((string) ($data['default_locale'] ?? 'en')),
                'timezone' => trim((string) ($data['timezone'] ?? 'Asia/Karachi')),
                'currency' => trim((string) ($data['currency'] ?? 'PKR')),
                'is_master_profile' => false,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $profile->branding()->create([
                'company_name' => $profile->name,
            ]);

            $this->seedModules($profile);
            $this->seedSuppliers($profile);

            $this->auditLogger->record(
                'dev_cp.client_profile.created',
                $profile,
                $developer,
                null,
                $request,
                ['slug' => $profile->slug],
            );

            return $profile->fresh(['branding', 'modules', 'suppliers']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     domain?: ?string,
     *     environment?: string,
     *     default_locale?: string,
     *     timezone?: string,
     *     currency?: string,
     *     is_active?: bool
     * }  $data
     */
    public function updateProfile(
        ClientProfile $profile,
        array $data,
        DeveloperUser $developer,
        ?Request $request = null,
    ): ClientProfile {
        $this->assertMasterEditConfirmed($profile, $request);

        $profile->update([
            'name' => trim($data['name']),
            'domain' => $this->nullableString($data['domain'] ?? null),
            'environment' => trim((string) ($data['environment'] ?? $profile->environment)),
            'default_locale' => trim((string) ($data['default_locale'] ?? $profile->default_locale)),
            'timezone' => trim((string) ($data['timezone'] ?? $profile->timezone)),
            'currency' => trim((string) ($data['currency'] ?? $profile->currency)),
            'is_active' => (bool) ($data['is_active'] ?? $profile->is_active),
        ]);

        $this->auditLogger->record(
            'dev_cp.client_profile.updated',
            $profile,
            $developer,
            null,
            $request,
            ['slug' => $profile->slug, 'section' => 'general'],
        );

        return $profile->fresh();
    }

    /**
     * @param  array{
     *     company_name: string,
     *     logo_path?: ?string,
     *     favicon_path?: ?string,
     *     primary_color?: ?string,
     *     secondary_color?: ?string,
     *     accent_color?: ?string,
     *     phone?: ?string,
     *     email?: ?string,
     *     address?: ?string,
     *     footer_text?: ?string
     * }  $data
     */
    public function updateBranding(
        ClientProfile $profile,
        array $data,
        DeveloperUser $developer,
        ?Request $request = null,
    ): ClientProfileBranding {
        $this->assertMasterEditConfirmed($profile, $request);

        $branding = $profile->branding()->updateOrCreate(
            ['client_profile_id' => $profile->id],
            [
                'company_name' => trim($data['company_name']),
                'logo_path' => $this->nullableString($data['logo_path'] ?? null),
                'favicon_path' => $this->nullableString($data['favicon_path'] ?? null),
                'primary_color' => $this->nullableString($data['primary_color'] ?? null),
                'secondary_color' => $this->nullableString($data['secondary_color'] ?? null),
                'accent_color' => $this->nullableString($data['accent_color'] ?? null),
                'phone' => $this->nullableString($data['phone'] ?? null),
                'email' => $this->nullableString($data['email'] ?? null),
                'address' => $this->nullableString($data['address'] ?? null),
                'footer_text' => $this->nullableString($data['footer_text'] ?? null),
            ],
        );

        $this->auditLogger->record(
            'dev_cp.client_profile.updated',
            $profile,
            $developer,
            null,
            $request,
            ['slug' => $profile->slug, 'section' => 'branding'],
        );

        return $branding;
    }

    /**
     * @param  array<string, bool>  $modules
     */
    public function updateModules(
        ClientProfile $profile,
        array $modules,
        DeveloperUser $developer,
        ?Request $request = null,
    ): ClientProfile {
        $this->assertMasterEditConfirmed($profile, $request);

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            if (! array_key_exists($moduleKey, $modules)) {
                continue;
            }

            $profile->modules()->updateOrCreate(
                ['module_key' => $moduleKey],
                ['enabled' => (bool) $modules[$moduleKey]],
            );
        }

        $this->auditLogger->record(
            'dev_cp.client_profile.updated',
            $profile,
            $developer,
            null,
            $request,
            ['slug' => $profile->slug, 'section' => 'modules'],
        );

        return $profile->fresh(['modules']);
    }

    /**
     * @param  array<string, array{enabled?: bool, mode?: ?string, config?: ?array<string, mixed>, credentials?: ?array<string, string>}>  $suppliers
     */
    public function updateSuppliers(
        ClientProfile $profile,
        array $suppliers,
        DeveloperUser $developer,
        ?Request $request = null,
    ): ClientProfile {
        $this->assertMasterEditConfirmed($profile, $request);

        foreach (SupplierProvider::cases() as $provider) {
            $key = $provider->value;
            if (! array_key_exists($key, $suppliers)) {
                continue;
            }

            $payload = $suppliers[$key];
            $existing = $profile->suppliers()->where('supplier_key', $key)->first();

            $attributes = [
                'enabled' => (bool) ($payload['enabled'] ?? false),
                'mode' => $this->nullableString($payload['mode'] ?? null),
                'config' => is_array($payload['config'] ?? null) ? $payload['config'] : null,
            ];

            $credentials = $payload['credentials'] ?? null;
            if (is_array($credentials) && $this->hasNonEmptyCredentialValues($credentials)) {
                $merged = is_array($existing?->credentials) ? $existing->credentials : [];
                foreach ($credentials as $credKey => $credValue) {
                    if (is_string($credKey) && is_string($credValue) && trim($credValue) !== '') {
                        $merged[$credKey] = trim($credValue);
                    }
                }
                $attributes['credentials'] = $merged !== [] ? $merged : null;
            }

            $profile->suppliers()->updateOrCreate(
                ['supplier_key' => $key],
                $attributes,
            );
        }

        $this->auditLogger->record(
            'dev_cp.client_profile.updated',
            $profile,
            $developer,
            null,
            $request,
            ['slug' => $profile->slug, 'section' => 'suppliers'],
        );

        return $profile->fresh(['suppliers']);
    }

    /**
     * @param  array{
     *     active_frontend_theme: string,
     *     active_admin_theme?: ?string,
     *     active_staff_theme?: ?string,
     *     asset_profile: string,
     *     preview_path?: ?string
     * }  $data
     */
    public function updateTheme(
        ClientProfile $profile,
        array $data,
        DeveloperUser $developer,
        ?Request $request = null,
    ): ClientProfile {
        $this->assertMasterEditConfirmed($profile, $request);

        $profile->update([
            'active_frontend_theme' => trim($data['active_frontend_theme']),
            'active_admin_theme' => $this->nullableString($data['active_admin_theme'] ?? null),
            'active_staff_theme' => $this->nullableString($data['active_staff_theme'] ?? null),
            'asset_profile' => trim($data['asset_profile']),
            'preview_path' => $this->nullableString($data['preview_path'] ?? null),
        ]);

        $this->auditLogger->record(
            'dev_cp.client_profile.updated',
            $profile,
            $developer,
            null,
            $request,
            ['slug' => $profile->slug, 'section' => 'theme'],
        );

        return $profile->fresh();
    }

    public function duplicateProfile(
        ClientProfile $source,
        string $newName,
        string $newSlug,
        bool $copyCredentials,
        DeveloperUser $developer,
        ?Request $request = null,
    ): ClientProfile {
        $this->assertMasterEditConfirmed($source, $request);

        $newSlug = Str::slug(trim($newSlug));
        $newName = trim($newName);

        if (ClientProfile::query()->where('slug', $newSlug)->exists()) {
            throw ValidationException::withMessages([
                'new_slug' => 'A client profile with this slug already exists.',
            ]);
        }

        return DB::transaction(function () use ($source, $newName, $newSlug, $copyCredentials, $developer, $request): ClientProfile {
            $source->loadMissing(['branding', 'modules', 'suppliers']);

            $duplicate = ClientProfile::query()->create([
                'name' => $newName,
                'slug' => $newSlug,
                'domain' => $source->domain,
                'preview_path' => $source->preview_path,
                'environment' => $source->environment,
                'active_frontend_theme' => $source->active_frontend_theme,
                'active_admin_theme' => $source->active_admin_theme,
                'active_staff_theme' => $source->active_staff_theme,
                'asset_profile' => $newSlug,
                'default_locale' => $source->default_locale,
                'timezone' => $source->timezone,
                'currency' => $source->currency,
                'is_master_profile' => false,
                'is_active' => $source->is_active,
            ]);

            if ($source->branding !== null) {
                $duplicate->branding()->create([
                    'company_name' => $source->branding->company_name,
                    'logo_path' => $source->branding->logo_path,
                    'favicon_path' => $source->branding->favicon_path,
                    'primary_color' => $source->branding->primary_color,
                    'secondary_color' => $source->branding->secondary_color,
                    'accent_color' => $source->branding->accent_color,
                    'phone' => $source->branding->phone,
                    'email' => $source->branding->email,
                    'address' => $source->branding->address,
                    'footer_text' => $source->branding->footer_text,
                    'config' => $source->branding->config,
                ]);
            } else {
                $duplicate->branding()->create(['company_name' => $newName]);
            }

            foreach ($source->modules as $module) {
                $duplicate->modules()->create([
                    'module_key' => $module->module_key,
                    'enabled' => $module->enabled,
                    'config' => $module->config,
                ]);
            }

            foreach ($source->suppliers as $supplier) {
                $duplicate->suppliers()->create([
                    'supplier_key' => $supplier->supplier_key,
                    'enabled' => $supplier->enabled,
                    'mode' => $supplier->mode,
                    'credentials' => $copyCredentials ? $supplier->credentials : null,
                    'config' => $supplier->config,
                ]);
            }

            $this->seedMissingModulesAndSuppliers($duplicate);

            $this->auditLogger->record(
                'dev_cp.client_profile.duplicated',
                $duplicate,
                $developer,
                null,
                $request,
                [
                    'source_slug' => $source->slug,
                    'new_slug' => $duplicate->slug,
                    'copy_credentials' => $copyCredentials,
                ],
            );

            return $duplicate->fresh(['branding', 'modules', 'suppliers']);
        });
    }

    public function assertMasterEditConfirmed(ClientProfile $profile, ?Request $request): void
    {
        if (! $profile->is_master_profile) {
            return;
        }

        if ($request === null || $request->input('confirm_master_edit') !== '1') {
            throw ValidationException::withMessages([
                'confirm_master_edit' => 'You must confirm editing the master deployment profile.',
            ]);
        }
    }

    /**
     * @return array<string, bool>
     */
    public function modulesStateForProfile(ClientProfile $profile): array
    {
        $profile->loadMissing('modules');
        $state = [];

        foreach (ClientProfileConfigReader::MODULE_KEYS as $key) {
            $module = $profile->modules->firstWhere('module_key', $key);
            $state[$key] = $module !== null ? (bool) $module->enabled : false;
        }

        return $state;
    }

    /**
     * @return array<string, ClientProfileSupplier>
     */
    public function suppliersStateForProfile(ClientProfile $profile): array
    {
        $profile->loadMissing('suppliers');
        $state = [];

        foreach (SupplierProvider::cases() as $provider) {
            $supplier = $profile->suppliers->firstWhere('supplier_key', $provider->value);
            if ($supplier === null) {
                $supplier = new ClientProfileSupplier([
                    'supplier_key' => $provider->value,
                    'enabled' => false,
                ]);
            }
            $state[$provider->value] = $supplier;
        }

        return $state;
    }

    private function seedModules(ClientProfile $profile): void
    {
        $defaults = $this->configReader->modulesFromConfig();

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            $profile->modules()->updateOrCreate(
                ['module_key' => $moduleKey],
                ['enabled' => (bool) ($defaults[$moduleKey] ?? false)],
            );
        }
    }

    private function seedSuppliers(ClientProfile $profile): void
    {
        foreach (SupplierProvider::cases() as $provider) {
            $profile->suppliers()->updateOrCreate(
                ['supplier_key' => $provider->value],
                ['enabled' => false],
            );
        }
    }

    private function seedMissingModulesAndSuppliers(ClientProfile $profile): void
    {
        $this->seedModules($profile);
        $this->seedSuppliers($profile);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function hasNonEmptyCredentialValues(array $credentials): bool
    {
        foreach ($credentials as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
