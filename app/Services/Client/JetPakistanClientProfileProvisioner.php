<?php

namespace App\Services\Client;

use App\Enums\SupplierProvider;
use App\Models\ClientProfile;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent provisioner for the JetPakistan client preview profile (slug: jetpk).
 */
final class JetPakistanClientProfileProvisioner
{
    public const SLUG = 'jetpk';

    public function __construct(
        private readonly ClientProfileConfigReader $configReader,
    ) {}

    /**
     * @return array{slug: string, created: bool, profile_id: int|null, updated: bool}
     */
    public function provision(bool $dryRun = false): array
    {
        if (! Schema::hasTable('client_profiles')) {
            throw new \RuntimeException('client_profiles table is missing. Run migrations first.');
        }

        $slug = self::SLUG;
        $existing = ClientProfile::query()->where('slug', $slug)->first();
        $created = $existing === null;

        if ($dryRun) {
            return [
                'slug' => $slug,
                'created' => $created,
                'profile_id' => $existing?->id,
                'updated' => ! $created,
            ];
        }

        return DB::transaction(function () use ($slug, $created, $existing): array {
            $profile = ClientProfile::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => 'JetPakistan',
                    'domain' => 'jetpakistan.com',
                    'preview_path' => '/jetpk',
                    'environment' => 'production',
                    'active_frontend_theme' => 'jetpakistan',
                    'active_admin_theme' => 'jetpakistan',
                    'active_staff_theme' => 'jetpakistan',
                    'asset_profile' => 'jetpk-assets',
                    'default_locale' => 'en',
                    'timezone' => 'Asia/Karachi',
                    'currency' => 'PKR',
                    'is_master_profile' => false,
                    'is_active' => true,
                ],
            );

            $profile->branding()->updateOrCreate(
                ['client_profile_id' => $profile->id],
                [
                    'company_name' => 'JetPakistan',
                    'logo_path' => 'logo/logo.svg',
                    'favicon_path' => 'favicon/favicon.ico',
                    'primary_color' => '#00843D',
                    'secondary_color' => '#00A651',
                    'accent_color' => '#FDB913',
                    'phone' => '',
                    'email' => 'support@jetpakistan.com',
                    'address' => 'Karachi, Pakistan',
                    'footer_text' => 'JetPakistan — your gateway to seamless travel.',
                    'config' => [
                        'website' => 'https://www.jetpakistan.com',
                    ],
                ],
            );

            $this->seedModules($profile);
            $this->seedSuppliers($profile);

            return [
                'slug' => $slug,
                'created' => $created,
                'profile_id' => $profile->id,
                'updated' => $existing !== null,
            ];
        });
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
}
