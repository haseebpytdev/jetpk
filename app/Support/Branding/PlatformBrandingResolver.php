<?php

namespace App\Support\Branding;

use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencySetting;
use App\Support\Agencies\AgencyPrefixService;
use Illuminate\Support\Facades\Schema;

/**
 * Single admin-managed source of truth for platform company name, prefixes, and email sender display name.
 */
final class PlatformBrandingResolver
{
    public const META_CUSTOMER_REFERENCE_PREFIX = 'customer_reference_prefix';

    public const META_AGENT_REFERENCE_PREFIX = 'agent_reference_prefix';

    public const META_HEADER_LOGO_HEIGHT = 'header_logo_height';

    public const DEFAULT_HEADER_LOGO_HEIGHT = 36;

    public const MIN_HEADER_LOGO_HEIGHT = 24;

    public const MAX_HEADER_LOGO_HEIGHT = 72;

    public const DEFAULT_COMPANY_PREFIX = 'OTA';

    public const DEFAULT_CUSTOMER_PREFIX = 'CU';

    public const DEFAULT_AGENT_PREFIX = 'AG';

    public static function forPlatform(): PlatformBranding
    {
        return self::forAgency(self::platformAgency());
    }

    public static function forAgency(?Agency $agency = null): PlatformBranding
    {
        $agency ??= self::platformAgency();
        $settings = $agency !== null ? self::agencySettings($agency) : null;
        $communication = $agency !== null ? self::communicationSettings($agency) : null;

        return self::build($agency, $settings, $communication);
    }

    public static function companyPrefixForAgencyId(?int $agencyId): string
    {
        if ($agencyId === null || ! Schema::hasTable('agencies')) {
            return self::DEFAULT_COMPANY_PREFIX;
        }

        $agency = Agency::query()->find($agencyId);

        return self::forAgency($agency)->companyPrefix();
    }

    public static function applyRuntimeConfig(): void
    {
        $branding = self::forPlatform();

        config(['app.name' => $branding->companyName()]);
        config(['mail.from.name' => $branding->emailFromName()]);
    }

    /**
     * @return list<string>
     */
    public static function lookupReferenceCandidates(string $input): array
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return [];
        }

        $candidates = [$trimmed];
        $suffix = self::extractSuffixFromDisplayInput($trimmed);

        if ($suffix !== '' && $suffix !== $trimmed) {
            $candidates[] = $suffix;
            $candidates[] = self::DEFAULT_COMPANY_PREFIX.'-'.$suffix;

            $platform = self::forPlatform();
            $candidates[] = $platform->companyPrefix().'-'.$suffix;
        }

        return array_values(array_unique(array_filter($candidates, fn (string $value): bool => $value !== '')));
    }

    public static function extractSuffixFromDisplayInput(string $input): string
    {
        $raw = trim($input);
        if ($raw === '') {
            return '';
        }

        $branding = self::forPlatform();
        $companyPrefix = $branding->companyPrefix();
        $customerPrefix = strtoupper($branding->customerPrefix());
        $agentPrefix = strtoupper($branding->agentPrefix());
        $upper = strtoupper($raw);
        $parts = explode('-', $upper);

        if (count($parts) >= 3 && in_array($parts[1], [$customerPrefix, $agentPrefix], true)) {
            return implode('-', array_slice(explode('-', $raw), 2));
        }

        if (str_starts_with($upper, strtoupper($companyPrefix).'-')) {
            $remainder = substr($raw, strlen($companyPrefix) + 1);
            if (preg_match('/^('.$customerPrefix.'|'.$agentPrefix.')-(.+)$/i', $remainder, $matches) === 1) {
                return $matches[2];
            }

            return $remainder;
        }

        if (str_starts_with($upper, self::DEFAULT_COMPANY_PREFIX.'-')) {
            return substr($raw, strlen(self::DEFAULT_COMPANY_PREFIX) + 1);
        }

        $dashPos = strpos($raw, '-');

        return $dashPos !== false ? substr($raw, $dashPos + 1) : $raw;
    }

    public static function customerReferencePrefix(?AgencySetting $settings): string
    {
        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $prefix = AgencyPrefixService::sanitizePrefix((string) ($meta[self::META_CUSTOMER_REFERENCE_PREFIX] ?? ''));

        return strlen($prefix) >= 2 ? $prefix : self::DEFAULT_CUSTOMER_PREFIX;
    }

    public static function agentReferencePrefix(?AgencySetting $settings): string
    {
        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $prefix = AgencyPrefixService::sanitizePrefix((string) ($meta[self::META_AGENT_REFERENCE_PREFIX] ?? ''));

        return strlen($prefix) >= 2 ? $prefix : self::DEFAULT_AGENT_PREFIX;
    }

    public static function headerLogoHeight(?AgencySetting $settings): int
    {
        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $stored = $meta[self::META_HEADER_LOGO_HEIGHT] ?? null;

        if ($stored === null || $stored === '') {
            return self::DEFAULT_HEADER_LOGO_HEIGHT;
        }

        return self::clampHeaderLogoHeight((int) $stored);
    }

    public static function clampHeaderLogoHeight(int $value): int
    {
        return max(self::MIN_HEADER_LOGO_HEIGHT, min(self::MAX_HEADER_LOGO_HEIGHT, $value));
    }

    protected static function build(
        ?Agency $agency,
        ?AgencySetting $settings,
        ?AgencyCommunicationSetting $communication,
    ): PlatformBranding {
        $companyName = BrandDisplayResolver::displayName($settings, null);
        $companyPrefix = $agency !== null
            ? (AgencyPrefixService::storedPrefix($agency) ?? self::DEFAULT_COMPANY_PREFIX)
            : self::DEFAULT_COMPANY_PREFIX;

        $client = config('ota-client', []);
        $brand = config('ota-brand', []);
        $mailFrom = config('mail.from', []);

        $emailFromName = self::resolveEmailFromName($communication, $settings, $mailFrom, $client, $brand);

        return new PlatformBranding(
            companyName: $companyName,
            companyPrefix: $companyPrefix,
            customerPrefix: self::customerReferencePrefix($settings),
            agentPrefix: self::agentReferencePrefix($settings),
            emailFromName: $emailFromName,
            supportEmail: self::firstNonEmptyString(
                $settings?->support_email,
                $client['support_email'] ?? null,
                $brand['support_email'] ?? null,
            ),
            supportPhone: self::firstNonEmptyString(
                $settings?->support_phone,
                $client['support_phone'] ?? null,
                $brand['support_phone'] ?? null,
            ),
            supportWhatsapp: self::firstNonEmptyString($settings?->support_whatsapp),
        );
    }

    protected static function platformAgency(): ?Agency
    {
        $slug = trim((string) config('ota.default_agency_slug', ''));
        if ($slug === '' || ! Schema::hasTable('agencies')) {
            return null;
        }

        return Agency::query()
            ->where('slug', $slug)
            ->with(['agencySetting', 'communicationSetting'])
            ->first();
    }

    protected static function agencySettings(Agency $agency): ?AgencySetting
    {
        if ($agency->relationLoaded('agencySetting')) {
            return $agency->agencySetting;
        }

        if (! Schema::hasTable('agency_settings')) {
            return null;
        }

        return AgencySetting::query()->where('agency_id', $agency->id)->first();
    }

    protected static function communicationSettings(Agency $agency): ?AgencyCommunicationSetting
    {
        if ($agency->relationLoaded('communicationSetting')) {
            return $agency->communicationSetting;
        }

        if (! Schema::hasTable('agency_communication_settings')) {
            return null;
        }

        return AgencyCommunicationSetting::query()->where('agency_id', $agency->id)->first();
    }

    protected static function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) && ! is_numeric($candidate)) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mailFrom
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $brand
     */
    protected static function resolveEmailFromName(
        ?AgencyCommunicationSetting $communication,
        ?AgencySetting $settings,
        array $mailFrom,
        array $client,
        array $brand,
    ): string {
        if (self::isJetpkDedicatedDeployment()) {
            return self::firstNonEmptyString(
                self::rejectJetpkLegacyMailAbbreviation($mailFrom['name'] ?? null),
                self::rejectJetpkLegacyMailAbbreviation($communication?->mail_from_name),
                self::rejectJetpkLegacyMailAbbreviation($settings?->display_name),
                self::rejectJetpkLegacyMailAbbreviation($brand['product_name'] ?? null),
                self::rejectJetpkLegacyMailAbbreviation($brand['name'] ?? null),
                self::rejectJetpkLegacyMailAbbreviation($client['agency_name'] ?? null),
                self::rejectJetpkLegacyMailAbbreviation(config('app.name')),
                'JetPakistan',
            ) ?? 'JetPakistan';
        }

        return self::firstNonEmptyString(
            $communication?->mail_from_name,
            $settings?->display_name,
            $mailFrom['name'] ?? null,
            $brand['product_name'] ?? null,
            $brand['name'] ?? null,
            $client['agency_name'] ?? null,
            config('app.name'),
        ) ?? 'Travel';
    }

    protected static function isJetpkDedicatedDeployment(): bool
    {
        if (function_exists('ota_single_client_root_slug') && ota_single_client_root_slug() === 'jetpk') {
            return true;
        }

        return strtolower(trim((string) config('ota_client.slug', ''))) === 'jetpk';
    }

    protected static function rejectJetpkLegacyMailAbbreviation(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || strcasecmp($trimmed, 'JetPk') === 0) {
            return null;
        }

        return $trimmed;
    }
}
