<?php

namespace App\Services\Ui;

use App\Models\DeveloperUser;
use App\Models\UiLayerSetting;
use App\Support\Ui\UiLayerRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Persists UI layer enable/disable overrides for Developer CP.
 */
class UiLayerSettingsService
{
    public const CACHE_KEY = 'ui.layer_settings.v1';

    private const CACHE_TTL_SECONDS = 3600;

    public function isEnabled(string $key): bool
    {
        $layer = UiLayerRegistry::find($key);
        if ($layer === null) {
            throw new InvalidArgumentException("Unknown UI layer key: {$key}");
        }

        $envOverride = env($layer->envVarName());
        if ($envOverride !== null && $envOverride !== '') {
            return filter_var($envOverride, FILTER_VALIDATE_BOOL);
        }

        $overrides = $this->overrides();
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        return $layer->defaultEnabled;
    }

    /**
     * @return array{
     *     config_default: bool,
     *     env_override: bool|null,
     *     db_override: bool|null,
     *     db_row_exists: bool,
     *     effective_enabled: bool,
     *     notes: string|null
     * }
     */
    public function effectiveStateFor(string $key): array
    {
        $layer = UiLayerRegistry::find($key);
        if ($layer === null) {
            throw new InvalidArgumentException("Unknown UI layer key: {$key}");
        }

        $row = $this->settingRow($key);
        $envRaw = env($layer->envVarName());
        $envOverride = ($envRaw !== null && $envRaw !== '')
            ? filter_var($envRaw, FILTER_VALIDATE_BOOL)
            : null;

        return [
            'config_default' => $layer->defaultEnabled,
            'env_override' => $envOverride,
            'db_override' => $row !== null ? $row->enabled : null,
            'db_row_exists' => $row !== null,
            'effective_enabled' => $this->isEnabled($key),
            'notes' => $row?->notes,
        ];
    }

    /**
     * @return array<string, bool> layer keys with DB override rows only
     */
    public function overrides(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => UiLayerSetting::query()
                ->pluck('enabled', 'layer_key')
                ->all()
        );
    }

    /**
     * @param  array<string, bool|int|string>  $changes
     */
    public function applyChanges(array $changes, DeveloperUser $actor, Request $request): void
    {
        $normalized = $this->normalizeChanges($changes);
        if ($normalized === []) {
            return;
        }

        DB::transaction(function () use ($normalized, $actor): void {
            foreach ($normalized as $key => $enabled) {
                UiLayerSetting::query()->updateOrCreate(
                    ['layer_key' => $key],
                    [
                        'enabled' => $enabled,
                        'updated_by_developer_user_id' => $actor->id,
                    ]
                );
            }
        });

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, bool|int|string>  $changes
     * @return array<string, bool>
     */
    private function normalizeChanges(array $changes): array
    {
        $normalized = [];

        foreach ($changes as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || UiLayerRegistry::find($key) === null) {
                throw new InvalidArgumentException("Unknown UI layer key: {$key}");
            }

            $normalized[$key] = filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $normalized;
    }

    private function settingRow(string $key): ?UiLayerSetting
    {
        return UiLayerSetting::query()->where('layer_key', $key)->first();
    }
}
