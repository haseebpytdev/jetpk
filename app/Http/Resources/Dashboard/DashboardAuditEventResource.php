<?php

namespace App\Http\Resources\Dashboard;

use App\Models\AuditLog;
use App\Support\Dashboard\AuditFieldMasker;

final class DashboardAuditEventResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(AuditLog $log): array
    {
        $properties = AuditFieldMasker::sanitizeProperties($log->properties);
        $category = self::category((string) $log->action);

        return [
            'id' => self::publicId($log),
            'occurredAt' => $log->created_at?->toIso8601String(),
            'category' => $category,
            'type' => (string) $log->action,
            'eventLabel' => self::eventLabel((string) $log->action),
            'actorName' => $log->user?->name ?? 'System',
            'actorType' => $log->user_id ? 'dashboardUser' : 'system',
            'targetType' => self::targetType($log->auditable_type),
            'targetLabel' => self::targetLabel($log),
            'sourceModule' => $category,
            'severity' => self::severity((string) $log->action),
            'outcome' => 'success',
            'risk' => self::risk((string) $log->action),
            'authorization' => 'allowed',
            'channel' => 'dashboard',
            'validationState' => 'valid',
            'maskedNetworkRange' => AuditFieldMasker::maskNetworkRange($log->ip_address),
            'userAgentSummary' => AuditFieldMasker::summarizeUserAgent($log->user_agent),
            'previewOnly' => true,
            'retentionMetadata' => [
                'category' => 'operational',
                'retentionDays' => 365,
                'purgeEligibleAt' => $log->created_at?->copy()->addYear()->toIso8601String(),
            ],
            'permissionKey' => self::permissionKey($category),
            'changeSummary' => isset($properties['summary']) ? (string) $properties['summary'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(AuditLog $log): array
    {
        $base = self::fromModel($log);
        $properties = AuditFieldMasker::sanitizeProperties($log->properties);

        return [
            ...$base,
            'actor' => [
                'actorType' => $base['actorType'],
                'userId' => $log->user ? DashboardUserResource::publicId($log->user) : null,
                'displayName' => $base['actorName'],
                'roleLabel' => $log->user?->isPlatformAdmin() ? 'Super Administrator' : 'Staff',
                'department' => 'Operations',
                'status' => 'active',
                'highRiskAccess' => (bool) $log->user?->isPlatformAdmin(),
            ],
            'target' => [
                'type' => $base['targetType'],
                'id' => (string) ($log->auditable_id ?? '—'),
                'label' => $base['targetLabel'],
            ],
            'metadata' => [
                'maskedIp' => AuditFieldMasker::maskIp($log->ip_address),
                'maskedNetworkRange' => $base['maskedNetworkRange'],
                'userAgentSummary' => $base['userAgentSummary'],
                'channel' => 'dashboard',
                'scope' => 'allRecords',
                'module' => $base['sourceModule'],
                'route' => null,
                'correlationId' => null,
                'referenceLabel' => null,
                'retentionCategory' => 'operational',
                'safeProperties' => $properties,
            ],
        ];
    }

    public static function publicId(AuditLog $log): string
    {
        return 'JP-AUD-'.str_pad((string) $log->id, 4, '0', STR_PAD_LEFT);
    }

    private static function category(string $action): string
    {
        if (str_contains($action, 'user') || str_contains($action, 'login')) {
            return 'users';
        }
        if (str_contains($action, 'booking')) {
            return 'bookings';
        }
        if (str_contains($action, 'payment')) {
            return 'payments';
        }
        if (str_contains($action, 'cms') || str_contains($action, 'page')) {
            return 'cms';
        }
        if (str_contains($action, 'setting')) {
            return 'settings';
        }

        return 'security';
    }

    private static function eventLabel(string $action): string
    {
        return ucwords(str_replace(['.', '_'], ' ', $action));
    }

    private static function severity(string $action): string
    {
        return str_contains($action, 'failed') || str_contains($action, 'denied') ? 'warning' : 'info';
    }

    private static function risk(string $action): string
    {
        return str_contains($action, 'permission') || str_contains($action, 'role') ? 'elevated' : 'standard';
    }

    private static function permissionKey(string $category): ?string
    {
        return match ($category) {
            'users' => 'users.view',
            'settings' => 'settings.view',
            'cms' => 'cms.view',
            default => 'audit.view',
        };
    }

    private static function targetType(?string $type): string
    {
        if ($type === null || $type === '') {
            return 'system';
        }

        return class_basename($type);
    }

    private static function targetLabel(AuditLog $log): string
    {
        if ($log->auditable_type) {
            return class_basename((string) $log->auditable_type).' #'.(string) ($log->auditable_id ?? '—');
        }

        return 'System event';
    }
}
