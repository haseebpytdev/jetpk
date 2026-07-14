<?php

namespace App\Services\Platform;

use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\PlatformModuleSettingChange;
use App\Services\Security\SecurityEventLogger;
use App\Support\Platform\PlatformModuleDependencyValidation;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Persists planned platform module states for Developer CP (Sprint 8G+; presets Sprint 8Q).
 */
class PlatformModuleSettingsService
{
    public const CACHE_KEY = 'platform.module_settings.v1';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @return array<string, bool> module key => effective enabled
     */
    public function states(): array
    {
        $states = [];
        foreach (PlatformModuleRegistry::all() as $module) {
            $states[$module->key] = $this->stateFor($module->key);
        }

        return $states;
    }

    public function stateFor(string $key): bool
    {
        $module = PlatformModuleRegistry::find($key);
        if ($module === null) {
            throw new InvalidArgumentException("Unknown platform module key: {$key}");
        }

        $overrides = $this->overrides();

        return $overrides[$key] ?? $module->defaultEnabled;
    }

    /**
     * @return array{
     *     registry_default: bool,
     *     db_override: bool|null,
     *     db_row_exists: bool,
     *     effective_enabled: bool,
     *     locked: bool,
     *     notes: string|null
     * }
     */
    public function effectiveStateFor(string $key): array
    {
        $module = PlatformModuleRegistry::find($key);
        if ($module === null) {
            throw new InvalidArgumentException("Unknown platform module key: {$key}");
        }

        $row = $this->settingRow($key);

        return [
            'registry_default' => $module->defaultEnabled,
            'db_override' => $row !== null ? $row->enabled : null,
            'db_row_exists' => $row !== null,
            'effective_enabled' => $this->stateFor($key),
            'locked' => $row?->locked ?? false,
            'notes' => $row?->notes,
        ];
    }

    /**
     * @return array<string, bool> module keys with DB override rows only
     */
    public function overrides(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => PlatformModuleSetting::query()
                ->pluck('enabled', 'module_key')
                ->all()
        );
    }

    /**
     * @param  array<string, bool|int|string>  $changes
     */
    public function validateChanges(array $changes): PlatformModuleDependencyValidation
    {
        $normalized = $this->normalizeChanges($changes);
        if ($normalized instanceof PlatformModuleDependencyValidation) {
            return $normalized;
        }

        $postChange = $this->states();
        foreach ($normalized as $key => $enabled) {
            $postChange[$key] = $enabled;
        }

        return PlatformModuleRegistry::validateDependencies($postChange);
    }

    /**
     * @param  array<string, bool|int|string>  $changes
     */
    public function applyChanges(
        array $changes,
        DeveloperUser $actor,
        Request $request,
        string $source = 'manual',
        ?string $presetKey = null,
    ): PlatformModuleDependencyValidation {
        $validation = $this->validateChanges($changes);
        if (! $validation->isValid()) {
            return $validation;
        }

        $normalized = $this->normalizeChanges($changes);
        if ($normalized instanceof PlatformModuleDependencyValidation) {
            return $normalized;
        }

        $notes = $this->normalizeNotes($request->input('notes', []));

        DB::transaction(function () use ($normalized, $notes, $actor, $request, $source, $presetKey): void {
            $beforeEffective = $this->statesWithoutCache();

            foreach (PlatformModuleRegistry::all() as $module) {
                if (! array_key_exists($module->key, $normalized)) {
                    continue;
                }

                $targetEnabled = $normalized[$module->key];
                $this->persistModuleState($module->key, $targetEnabled, $module->defaultEnabled, $actor, $notes[$module->key] ?? null);
            }

            foreach ($beforeEffective as $moduleKey => $oldEnabled) {
                $newEnabled = $this->effectiveEnabledForKey($moduleKey);
                if ($oldEnabled === $newEnabled) {
                    continue;
                }

                $this->logChange(
                    actor: $actor,
                    request: $request,
                    moduleKey: $moduleKey,
                    oldEnabled: $oldEnabled,
                    newEnabled: $newEnabled,
                    source: $source,
                    presetKey: $presetKey,
                );
            }
        });

        $this->forgetCache();

        $this->recordModuleChangeAudit($actor, $request, $source, $presetKey);

        return $validation;
    }

    public function applyPreset(string $presetKey, DeveloperUser $actor, Request $request): PlatformModuleDependencyValidation
    {
        return $this->applyChanges(
            changes: PlatformModuleRegistry::presetModules($presetKey),
            actor: $actor,
            request: $request,
            source: 'preset',
            presetKey: $presetKey,
        );
    }

    public function resetToDefaults(DeveloperUser $actor, Request $request): void
    {
        $this->clearAllOverrides($actor, $request, 'reset');
        $this->recordModuleChangeAudit($actor, $request, 'reset', null);
    }

    public function allEnabledEmergencyReset(DeveloperUser $actor, Request $request): void
    {
        $this->clearAllOverrides($actor, $request, 'emergency');
        $this->recordModuleChangeAudit($actor, $request, 'emergency', null);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, bool|int|string>  $changes
     * @return array<string, bool>|PlatformModuleDependencyValidation
     */
    private function normalizeChanges(array $changes): array|PlatformModuleDependencyValidation
    {
        $normalized = [];

        foreach ($changes as $key => $value) {
            $moduleKey = (string) $key;
            $module = PlatformModuleRegistry::find($moduleKey);

            if ($module === null) {
                return new PlatformModuleDependencyValidation(false, [[
                    'module' => $moduleKey,
                    'code' => 'unknown_module',
                    'message' => 'Unknown module key.',
                ]]);
            }

            $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabled === null) {
                return new PlatformModuleDependencyValidation(false, [[
                    'module' => $moduleKey,
                    'code' => 'invalid_state',
                    'message' => 'Module state must be a boolean.',
                ]]);
            }

            if (! $enabled && $module->protected) {
                return new PlatformModuleDependencyValidation(false, [[
                    'module' => $moduleKey,
                    'code' => 'protected_module',
                    'message' => "{$module->label} cannot be disabled.",
                ]]);
            }

            $normalized[$moduleKey] = $enabled;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $notes
     * @return array<string, string|null>
     */
    private function normalizeNotes(array $notes): array
    {
        $normalized = [];
        foreach ($notes as $key => $value) {
            $moduleKey = (string) $key;
            if (PlatformModuleRegistry::find($moduleKey) === null) {
                continue;
            }
            $text = is_string($value) ? trim($value) : '';
            $normalized[$moduleKey] = $text !== '' ? $text : null;
        }

        return $normalized;
    }

    private function persistModuleState(
        string $moduleKey,
        bool $targetEnabled,
        bool $registryDefault,
        DeveloperUser $actor,
        ?string $notes,
    ): void {
        $existing = PlatformModuleSetting::query()->where('module_key', $moduleKey)->first();

        if ($targetEnabled === $registryDefault) {
            if ($existing !== null) {
                $existing->delete();
            }

            return;
        }

        PlatformModuleSetting::query()->updateOrCreate(
            ['module_key' => $moduleKey],
            [
                'enabled' => $targetEnabled,
                'notes' => $notes,
                'updated_by_developer_user_id' => $actor->id,
            ]
        );
    }

    private function clearAllOverrides(DeveloperUser $actor, Request $request, string $source): void
    {
        DB::transaction(function () use ($actor, $request, $source): void {
            $rows = PlatformModuleSetting::query()->get();

            foreach ($rows as $row) {
                $module = PlatformModuleRegistry::find($row->module_key);
                if ($module === null) {
                    continue;
                }

                $oldEnabled = $row->enabled;
                $newEnabled = $module->defaultEnabled;

                $this->logChange(
                    actor: $actor,
                    request: $request,
                    moduleKey: $row->module_key,
                    oldEnabled: $oldEnabled,
                    newEnabled: $newEnabled,
                    source: $source,
                    presetKey: null,
                );
            }

            PlatformModuleSetting::query()->delete();
        });

        $this->forgetCache();
    }

    private function logChange(
        DeveloperUser $actor,
        Request $request,
        string $moduleKey,
        bool $oldEnabled,
        bool $newEnabled,
        string $source,
        ?string $presetKey,
    ): void {
        PlatformModuleSettingChange::query()->create([
            'developer_user_id' => $actor->id,
            'module_key' => $moduleKey,
            'old_enabled' => $oldEnabled,
            'new_enabled' => $newEnabled,
            'source' => $source,
            'preset_key' => $presetKey,
            'validation_passed' => true,
            'validation_violations' => null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 250),
        ]);
    }

    private function settingRow(string $key): ?PlatformModuleSetting
    {
        return PlatformModuleSetting::query()->where('module_key', $key)->first();
    }

    /**
     * @return array<string, bool>
     */
    private function statesWithoutCache(): array
    {
        $this->forgetCache();

        return $this->states();
    }

    private function effectiveEnabledForKey(string $key): bool
    {
        $module = PlatformModuleRegistry::find($key);
        if ($module === null) {
            return false;
        }

        $row = PlatformModuleSetting::query()->where('module_key', $key)->first();

        return $row !== null ? $row->enabled : $module->defaultEnabled;
    }

    private function recordModuleChangeAudit(
        DeveloperUser $actor,
        Request $request,
        string $source,
        ?string $presetKey,
    ): void {
        try {
            app(PlatformAuditLogger::class)->record(
                action: 'platform.module_settings_changed',
                developer: $actor,
                request: $request,
                properties: [
                    'source' => $source,
                    'preset_key' => $presetKey,
                ],
            );

            app(SecurityEventLogger::class)->record(
                eventType: 'module.changed',
                outcome: 'success',
                actor: $actor,
                request: $request,
                metadata: [
                    'source' => $source,
                    'preset_key' => $presetKey,
                ],
            );
        } catch (\Throwable) {
            // fail-safe
        }
    }
}
