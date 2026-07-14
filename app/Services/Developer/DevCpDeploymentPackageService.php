<?php

namespace App\Services\Developer;

use App\Models\PlatformFeatureFlag;

/**
 * Records which deployment package preset is active for this OTA install (global scope).
 */
class DevCpDeploymentPackageService
{
    public const KEY_PREFIX = 'deployment_package:';

    public function markApplied(string $packageKey): void
    {
        PlatformFeatureFlag::query()
            ->where('scope', 'global')
            ->whereNull('agency_id')
            ->where('key', 'like', self::KEY_PREFIX.'%')
            ->delete();

        PlatformFeatureFlag::query()->updateOrCreate(
            [
                'key' => self::KEY_PREFIX.$packageKey,
                'scope' => 'global',
                'agency_id' => null,
            ],
            ['enabled' => true]
        );
    }

    public function currentPackageKey(): ?string
    {
        $row = PlatformFeatureFlag::query()
            ->where('scope', 'global')
            ->whereNull('agency_id')
            ->where('key', 'like', self::KEY_PREFIX.'%')
            ->where('enabled', true)
            ->first();

        if ($row === null) {
            return null;
        }

        return str_starts_with($row->key, self::KEY_PREFIX)
            ? substr($row->key, strlen(self::KEY_PREFIX))
            : null;
    }
}
