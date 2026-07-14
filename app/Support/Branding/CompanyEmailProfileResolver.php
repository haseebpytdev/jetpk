<?php

namespace App\Support\Branding;

use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencySetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Single read layer for platform company / email identity (default agency settings + config fallbacks).
 *
 * Resolution order per field is documented on {@see build()}. Never uses the authenticated admin user.
 */
class CompanyEmailProfileResolver
{
    public static function resolveForPlatform(): CompanyEmailProfile
    {
        return self::resolve(self::platformAgency());
    }

    public static function resolve(?Agency $agency = null): CompanyEmailProfile
    {
        $agency ??= self::platformAgency();

        $settings = $agency !== null ? self::agencySettings($agency) : null;
        $communication = $agency !== null ? self::communicationSettings($agency) : null;

        return self::build($settings, $communication, $agency);
    }

    protected static function build(
        ?AgencySetting $settings,
        ?AgencyCommunicationSetting $communication,
        ?Agency $agency,
    ): CompanyEmailProfile {
        $client = config('ota-client', []);
        $brand = config('ota-brand', []);
        $mailFrom = config('mail.from', []);

        $platformBranding = PlatformBrandingResolver::forAgency($agency);
        $name = $platformBranding->companyName();

        $legalName = self::firstNonEmptyString(
            $settings?->legal_name,
            $agency?->name,
        );

        $supportEmail = self::firstNonEmptyString(
            $settings?->support_email,
            $client['support_email'] ?? null,
            $brand['support_email'] ?? null,
        );

        $supportPhone = self::firstNonEmptyString(
            $settings?->support_phone,
            $client['support_phone'] ?? null,
            $brand['support_phone'] ?? null,
        );

        $websiteUrl = self::firstNonEmptyString(
            $settings?->website_url,
            self::websiteFromConfig($client, $brand),
        );

        $address = self::formatAddress($settings, $client);

        $theme = BrandDisplayResolver::themeColors($settings);

        $mailFromName = $platformBranding->emailFromName();

        $mailFromEmail = self::firstNonEmptyString(
            $communication?->mail_from_email,
            $mailFrom['address'] ?? null,
            $supportEmail,
        ) ?? 'hello@example.com';

        $replyToEmail = self::firstNonEmptyString(
            $communication?->reply_to_email,
            $supportEmail,
            $mailFrom['address'] ?? null,
        );

        $footerText = self::firstNonEmptyString(
            $settings?->footer_copyright,
            $settings?->footer_about,
            $client['footer_text'] ?? null,
            $brand['company_note'] ?? null,
        );

        return new CompanyEmailProfile(
            name: $name,
            legal_name: $legalName,
            logo_url: self::resolveLogoUrl($settings),
            support_email: $supportEmail,
            support_phone: $supportPhone,
            website_url: $websiteUrl,
            address: $address,
            primary_color: $theme['primary'],
            secondary_color: $theme['secondary'],
            mail_from_name: $mailFromName,
            mail_from_email: $mailFromEmail,
            reply_to_email: $replyToEmail,
            footer_text: $footerText,
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

    protected static function resolveLogoUrl(?AgencySetting $settings): ?string
    {
        $path = trim((string) ($settings?->logo_path ?? ''));
        if ($path === '') {
            return null;
        }

        return asset('storage/'.$path);
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $brand
     */
    protected static function websiteFromConfig(array $client, array $brand): ?string
    {
        $domain = trim((string) ($client['domain_preview'] ?? $brand['domain'] ?? ''));
        if ($domain === '') {
            return null;
        }

        if (Str::startsWith($domain, ['http://', 'https://'])) {
            return $domain;
        }

        return 'https://'.$domain;
    }

    /**
     * @param  array<string, mixed>  $client
     */
    protected static function formatAddress(?AgencySetting $settings, array $client): ?string
    {
        $parts = array_filter([
            self::trimOrNull($settings?->office_address),
            self::trimOrNull($settings?->city) ?? self::trimOrNull($client['office_city'] ?? null),
            self::trimOrNull($settings?->country),
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
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

    protected static function trimOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
