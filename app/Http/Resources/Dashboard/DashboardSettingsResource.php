<?php

namespace App\Http\Resources\Dashboard;

use App\Models\SupplierConnection;
use App\Support\Branding\PlatformBrandingResolver;

final class DashboardSettingsResource
{
    /**
     * @return array<string, mixed>
     */
    public static function overview(): array
    {
        return [
            'generalState' => 'ready',
            'securityPolicyState' => 'ready',
            'notificationState' => 'ready',
            'integrationState' => 'ready',
            'settingsRequiringReview' => 0,
            'highRiskPolicyWarnings' => 0,
            'incompleteMetadata' => 0,
            'lastFixtureRevision' => 'laravel-read-only',
            'categoryReadiness' => [
                ['section' => 'general', 'label' => 'General', 'ready' => true, 'issueCount' => 0],
                ['section' => 'security', 'label' => 'Security', 'ready' => true, 'issueCount' => 0],
                ['section' => 'notifications', 'label' => 'Notifications', 'ready' => true, 'issueCount' => 0],
                ['section' => 'integrations', 'label' => 'Integrations', 'ready' => true, 'issueCount' => 0],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function general(): array
    {
        $branding = PlatformBrandingResolver::forPlatform();

        return [
            'organizationDisplayName' => (string) ($branding->companyName() ?? 'JetPakistan'),
            'publicSupportLabel' => 'Customer Support',
            'supportPhone' => '—',
            'supportEmail' => '—',
            'timezone' => (string) config('app.timezone', 'UTC'),
            'defaultCurrency' => 'PKR',
            'locale' => 'en-PK',
            'dateFormat' => 'YYYY-MM-DD',
            'operationalReferenceLabel' => 'JetPakistan OTA',
            'dashboardPaginationDefault' => 25,
            'reportingReferenceMetadata' => 'Laravel read-only metadata',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function security(): array
    {
        return [
            'mfaRequirementPolicy' => 'Platform administrators require MFA when enabled.',
            'privilegedRoleMfaPolicy' => 'Privileged roles should enable MFA.',
            'passwordMinLength' => 8,
            'passwordComplexityPolicy' => 'Minimum length with mixed character classes recommended.',
            'sessionDurationHours' => (int) floor(((int) config('session.lifetime', 120)) / 60),
            'idleTimeoutMinutes' => (int) config('session.lifetime', 120),
            'failedLoginThreshold' => 5,
            'lockoutDurationMinutes' => 30,
            'invitationExpiryDays' => 7,
            'highRiskApprovalPolicy' => 'High-risk actions require elevated approval in production workflows.',
            'auditRetentionDays' => 365,
            'sessionConcurrencyPolicy' => 'Single active staff session recommended.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function notifications(): array
    {
        return [
            'categories' => [
                ['key' => 'booking_updates', 'label' => 'Booking updates', 'enabled' => true, 'emailChannel' => true, 'dashboardChannel' => true, 'severityThreshold' => 'info', 'recipientRoles' => ['Operations Manager'], 'deliveryMode' => 'immediate'],
                ['key' => 'payment_alerts', 'label' => 'Payment alerts', 'enabled' => true, 'emailChannel' => true, 'dashboardChannel' => true, 'severityThreshold' => 'warning', 'recipientRoles' => ['Finance Officer'], 'deliveryMode' => 'immediate'],
                ['key' => 'security_events', 'label' => 'Security events', 'enabled' => true, 'emailChannel' => true, 'dashboardChannel' => true, 'severityThreshold' => 'critical', 'recipientRoles' => ['Super Administrator'], 'deliveryMode' => 'immediate'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function integrations(): array
    {
        $connections = SupplierConnection::query()
            ->select(['id', 'display_name', 'provider', 'environment', 'status', 'is_active'])
            ->limit(20)
            ->get();

        return [
            'integrations' => $connections->map(static function (SupplierConnection $connection): array {
                return [
                    'key' => 'supplier-'.$connection->id,
                    'displayName' => (string) ($connection->display_name ?: $connection->provider),
                    'channel' => strtoupper($connection->provider->value),
                    'enabled' => (bool) $connection->is_active,
                    'readinessStatus' => $connection->is_active ? 'configured' : 'disabled',
                    'environmentLabel' => $connection->environment?->value ?? 'sandbox',
                    'lastSyntheticCheck' => now()->subHours(6)->toIso8601String(),
                    'configurationCompleteness' => $connection->is_active ? 100 : 40,
                    'capabilitySummary' => 'Read-only integration metadata',
                    'warningState' => ! $connection->is_active,
                    'futureOwner' => 'Platform operations',
                ];
            })->values()->all(),
        ];
    }
}
