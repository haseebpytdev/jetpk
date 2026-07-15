<?php

namespace App\Services\Platform;

use App\Models\CompanyModuleEntitlement;
use App\Models\DeveloperUser;
use App\Models\PlatformPackage;
use App\Services\Security\SecurityEventLogger;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Per-agency module entitlements (company = Agency tenant).
 */
class CompanyModuleEntitlementService
{
    public const CACHE_PREFIX = 'platform.agency_entitlements.v1.';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * When no entitlement row exists, inherit global planned state.
     */
    public function isModuleEnabledForAgency(int $agencyId, string $moduleKey, bool $globalEnabled): bool
    {
        $row = $this->entitlementRow($agencyId, $moduleKey);

        if ($row === null) {
            return $globalEnabled;
        }

        if ($row->isExpired()) {
            return $globalEnabled;
        }

        return $row->enabled;
    }

    /**
     * @return array<string, bool|null> module_key => enabled|null (null = inherit global)
     */
    public function overridesForAgency(int $agencyId): array
    {
        return Cache::remember(
            self::CACHE_PREFIX.$agencyId,
            self::CACHE_TTL_SECONDS,
            function () use ($agencyId): array {
                $rows = CompanyModuleEntitlement::query()
                    ->where('agency_id', $agencyId)
                    ->get();

                $overrides = [];
                foreach ($rows as $row) {
                    if ($row->isExpired()) {
                        continue;
                    }
                    $overrides[$row->module_key] = $row->enabled;
                }

                return $overrides;
            }
        );
    }

    /**
     * @param  array<string, bool>  $moduleStates  module_key => enabled
     */
    public function applyPackageToAgency(
        PlatformPackage $package,
        int $agencyId,
        DeveloperUser $actor,
        Request $request,
        ?PlatformAuditLogger $auditLogger = null,
    ): int {
        $auditLogger ??= app(PlatformAuditLogger::class);
        $applied = 0;

        foreach ($package->modules()->get() as $packageModule) {
            if (! PlatformModuleRegistry::find($packageModule->module_key)) {
                continue;
            }

            CompanyModuleEntitlement::query()->updateOrCreate(
                [
                    'agency_id' => $agencyId,
                    'module_key' => $packageModule->module_key,
                ],
                [
                    'enabled' => $packageModule->enabled,
                    'expires_at' => null,
                    'source' => 'package:'.$package->key,
                    'assigned_by_developer_user_id' => $actor->id,
                ]
            );
            $applied++;
        }

        $this->forgetAgencyCache($agencyId);

        $auditLogger->record(
            action: 'company.package_assigned',
            subject: $package,
            developer: $actor,
            agencyId: $agencyId,
            request: $request,
            properties: [
                'package_key' => $package->key,
                'modules_applied' => $applied,
            ],
        );

        app(SecurityEventLogger::class)->record(
            eventType: 'module.package_changed',
            outcome: 'success',
            actor: $actor,
            agencyId: $agencyId,
            request: $request,
            metadata: [
                'package_key' => $package->key,
                'modules_applied' => $applied,
            ],
        );

        return $applied;
    }

    public function setModuleEntitlement(
        int $agencyId,
        string $moduleKey,
        bool $enabled,
        ?Carbon $expiresAt,
        DeveloperUser $actor,
        Request $request,
        string $source = 'manual',
    ): CompanyModuleEntitlement {
        if (PlatformModuleRegistry::find($moduleKey) === null) {
            throw new \InvalidArgumentException("Unknown module key: {$moduleKey}");
        }

        $entitlement = CompanyModuleEntitlement::query()->updateOrCreate(
            [
                'agency_id' => $agencyId,
                'module_key' => $moduleKey,
            ],
            [
                'enabled' => $enabled,
                'expires_at' => $expiresAt,
                'source' => $source,
                'assigned_by_developer_user_id' => $actor->id,
            ]
        );

        $this->forgetAgencyCache($agencyId);

        app(PlatformAuditLogger::class)->record(
            action: 'company.module_entitlement_changed',
            subject: $entitlement,
            developer: $actor,
            agencyId: $agencyId,
            request: $request,
            properties: [
                'module_key' => $moduleKey,
                'enabled' => $enabled,
                'expires_at' => $expiresAt?->toIso8601String(),
            ],
        );

        app(SecurityEventLogger::class)->record(
            eventType: 'module.changed',
            outcome: 'success',
            actor: $actor,
            agencyId: $agencyId,
            request: $request,
            metadata: [
                'module_key' => $moduleKey,
                'enabled' => $enabled,
            ],
        );

        return $entitlement;
    }

    public function forgetAgencyCache(int $agencyId): void
    {
        Cache::forget(self::CACHE_PREFIX.$agencyId);
    }

    private function entitlementRow(int $agencyId, string $moduleKey): ?CompanyModuleEntitlement
    {
        $overrides = $this->overridesForAgency($agencyId);

        if (! array_key_exists($moduleKey, $overrides)) {
            return CompanyModuleEntitlement::query()
                ->where('agency_id', $agencyId)
                ->where('module_key', $moduleKey)
                ->first();
        }

        return CompanyModuleEntitlement::query()
            ->where('agency_id', $agencyId)
            ->where('module_key', $moduleKey)
            ->first();
    }
}
