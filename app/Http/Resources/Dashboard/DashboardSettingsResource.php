<?php

namespace App\Http\Resources\Dashboard;

use App\Models\AgencySetting;
use App\Models\AgencyNotificationSetting;
use App\Models\SupplierConnection;
use App\Enums\OtaNotificationEvent;
use App\Support\Branding\PlatformBrandingResolver;
use App\Support\Suppliers\SupplierRegistry;

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
            'lastFixtureRevision' => 'platform-settings',
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
        $agency = null;
        try {
            $agency = \App\Models\Agency::query()
                ->where('slug', trim((string) config('ota.default_agency_slug', '')))
                ->first();
        } catch (\Throwable) {
            $agency = null;
        }
        $settings = $agency?->agencySetting ?? ($agency ? AgencySetting::query()->where('agency_id', $agency->id)->first() : null);
        $supportEmail = trim((string) ($branding->supportEmail() ?: $settings?->support_email ?: ''));
        $supportPhone = trim((string) ($branding->phone() ?: $settings?->support_phone ?: ''));
        $timezone = trim((string) ($settings?->timezone ?: 'Asia/Karachi'));
        $currency = strtoupper(trim((string) ($settings?->currency ?: 'PKR'))) ?: 'PKR';

        return [
            'organizationDisplayName' => (string) ($branding->companyName() ?: $settings?->display_name ?: 'JetPakistan'),
            'publicSupportLabel' => 'Customer Support',
            'supportPhone' => $supportPhone !== '' ? $supportPhone : '',
            'supportEmail' => $supportEmail !== '' ? $supportEmail : '',
            'timezone' => $timezone !== '' ? $timezone : 'Asia/Karachi',
            'defaultCurrency' => $currency,
            'locale' => 'en-PK',
            'dateFormat' => 'DD MMM YYYY',
            'operationalReferenceLabel' => 'JetPakistan OTA',
            'dashboardPaginationDefault' => 25,
            'reportingReferenceMetadata' => 'Organization profile / agency settings',
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
            'passwordMinLength' => (int) config('auth.password_min_length', 8),
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
        $agency = \App\Models\Agency::query()
            ->where('slug', trim((string) config('ota.default_agency_slug', '')))
            ->first();
        $rows = $agency
            ? AgencyNotificationSetting::query()->where('agency_id', $agency->id)->get()
            : collect();

        $categories = [];
        foreach (\App\Support\Communication\JetpkNotificationEventCategories::grouped() as $label => $events) {
            $keys = array_map(static fn (OtaNotificationEvent $event): string => $event->value, $events);
            $subset = $rows->whereIn('event_key', $keys);
            $enabled = $subset->contains(static fn (AgencyNotificationSetting $row): bool => (bool) $row->enabled);
            $email = $subset->contains(static fn (AgencyNotificationSetting $row): bool => (bool) $row->enabled && $row->channel === 'email');
            $dashboard = $subset->contains(static function (AgencyNotificationSetting $row): bool {
                $meta = is_array($row->meta) ? $row->meta : [];

                return (bool) ($meta['dashboard_channel'] ?? false);
            });
            $digest = $subset->contains(static fn (AgencyNotificationSetting $row): bool => $row->digest_mode === 'digest');
            $roles = $subset->pluck('recipient_scope')->filter()->unique()->values()->all();

            $categories[] = [
                'key' => \Illuminate\Support\Str::slug($label),
                'label' => $label,
                'enabled' => $enabled || $subset->isEmpty(),
                'emailChannel' => $email || $subset->isEmpty(),
                'dashboardChannel' => $dashboard,
                'severityThreshold' => in_array($label, ['Security', 'Payment'], true) ? 'warning' : 'notice',
                'recipientRoles' => $roles !== [] ? $roles : ['admin'],
                'deliveryMode' => $digest ? 'digest' : 'immediate',
                'eventKeys' => $keys,
            ];
        }

        return ['categories' => $categories];
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
                $state = SupplierRegistry::stateForConnection($connection);

                return [
                    'key' => 'supplier-'.$connection->id,
                    'displayName' => (string) ($connection->display_name ?: $connection->provider?->value),
                    'channel' => strtoupper((string) ($connection->provider?->value ?? '')),
                    'enabled' => (bool) $connection->is_active,
                    'readinessStatus' => strtolower($state),
                    'environmentLabel' => $connection->environment?->value ?? 'sandbox',
                    'lastSyntheticCheck' => $connection->last_tested_at?->toIso8601String() ?? '',
                    'configurationCompleteness' => $state === SupplierRegistry::CONFIGURED_ENABLED ? 100 : ($state === SupplierRegistry::CONNECTION_NOT_CONFIGURED ? 20 : 60),
                    'capabilitySummary' => SupplierRegistry::businessLabel($state),
                    'warningState' => in_array($state, [SupplierRegistry::CONFIGURED_DISABLED, SupplierRegistry::CONNECTION_NOT_CONFIGURED], true),
                    'futureOwner' => 'Platform operations',
                    'registryState' => $state,
                ];
            })->values()->all(),
        ];
    }
}
