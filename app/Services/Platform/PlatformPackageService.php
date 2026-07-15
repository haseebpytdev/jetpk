<?php

namespace App\Services\Platform;

use App\Models\PlatformPackage;
use App\Models\PlatformPackageModule;
use App\Support\Platform\PlatformModuleRegistry;

/**
 * Platform package catalog and assignment helpers.
 */
class PlatformPackageService
{
    /**
     * @var array<string, array{label: string, description: string, preset_key: string}>
     */
    private const DEFAULT_PACKAGES = [
        'full_ota' => [
            'label' => 'Full OTA',
            'description' => 'Complete B2B + B2C deployment with suppliers and finance.',
            'preset_key' => 'b2b_b2c',
        ],
        'b2b_only' => [
            'label' => 'B2B Only',
            'description' => 'Agent portal and admin without public B2C checkout.',
            'preset_key' => 'b2b_only',
        ],
        'b2c_only' => [
            'label' => 'B2C Only',
            'description' => 'Public site and customer portal without agent tools.',
            'preset_key' => 'b2c_only',
        ],
        'maintenance_lite' => [
            'label' => 'Maintenance Lite',
            'description' => 'Public shell and admin access for maintenance windows.',
            'preset_key' => 'maintenance_lite',
        ],
    ];

    /**
     * Idempotent seed of default packages from registry presets.
     *
     * @return array{created: int, updated: int}
     */
    public function seedDefaults(): array
    {
        $created = 0;
        $updated = 0;

        foreach (self::DEFAULT_PACKAGES as $key => $meta) {
            $presetModules = PlatformModuleRegistry::presetModules($meta['preset_key']);
            if ($presetModules === []) {
                continue;
            }

            $package = PlatformPackage::query()->firstOrNew(['key' => $key]);
            $isNew = ! $package->exists;

            $package->fill([
                'label' => $meta['label'],
                'description' => $meta['description'],
                'is_active' => true,
            ]);
            $package->save();

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }

            foreach ($presetModules as $moduleKey => $enabled) {
                PlatformPackageModule::query()->updateOrCreate(
                    [
                        'platform_package_id' => $package->id,
                        'module_key' => (string) $moduleKey,
                    ],
                    ['enabled' => (bool) $enabled]
                );
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return list<PlatformPackage>
     */
    public function activePackages(): array
    {
        return PlatformPackage::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->all();
    }

    public function presetKeyForPackage(PlatformPackage $package): ?string
    {
        return self::DEFAULT_PACKAGES[$package->key]['preset_key'] ?? null;
    }
}
