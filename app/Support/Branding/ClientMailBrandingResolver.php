<?php

namespace App\Support\Branding;

use App\Support\Auth\ClientLoginOtpGate;

/**
 * Resolves client-scoped mail branding for transactional emails.
 *
 * Shared SMTP credentials (host, username, password, from address) may remain platform-wide.
 * Client-specific visible From Name and Reply-To must be set per mailable via this resolver.
 */
final class ClientMailBrandingResolver
{
    private const JETPK_REPLY_TO = 'ota@jetpakistan.pk';

    public static function resolve(?string $clientSlug = null): ClientMailBrandingProfile
    {
        $slug = self::normalizeSlug($clientSlug ?? ClientLoginOtpGate::resolvedClientSlug());

        if ($slug === 'jetpk') {
            return self::resolveJetPk();
        }

        if ($slug !== null && is_client_preview() && current_client_slug() === $slug) {
            return self::resolveFromPreviewContext($slug);
        }

        return self::resolvePlatformFallback($slug);
    }

    private static function resolveJetPk(): ClientMailBrandingProfile
    {
        $companyName = 'JetPakistan';
        $replyTo = self::JETPK_REPLY_TO;
        $supportEmail = self::JETPK_REPLY_TO;
        $logoUrl = null;

        if (is_client_preview()) {
            $branding = client_branding();
            $name = trim($branding->companyName());
            if ($name !== '') {
                $companyName = $name;
            }
            $email = trim($branding->email());
            if ($email !== '') {
                $replyTo = $email;
                $supportEmail = $email;
            }
            $logoUrl = $branding->logoUrl();
        }

        return new ClientMailBrandingProfile(
            clientSlug: 'jetpk',
            companyName: $companyName,
            mailFromName: $companyName,
            replyToEmail: $replyTo,
            supportEmail: $supportEmail,
            logoUrl: $logoUrl,
        );
    }

    private static function resolveFromPreviewContext(string $slug): ClientMailBrandingProfile
    {
        $branding = client_branding();
        $companyName = trim($branding->companyName());
        if ($companyName === '') {
            $companyName = (string) config('app.name', 'OTA');
        }

        $replyTo = self::firstNonEmpty(
            trim($branding->email()),
            CompanyEmailProfileResolver::resolveForPlatform()->reply_to_email,
        );

        return new ClientMailBrandingProfile(
            clientSlug: $slug,
            companyName: $companyName,
            mailFromName: $companyName,
            replyToEmail: $replyTo,
            supportEmail: trim($branding->email()) !== '' ? trim($branding->email()) : null,
            logoUrl: $branding->logoUrl(),
        );
    }

    private static function resolvePlatformFallback(?string $slug): ClientMailBrandingProfile
    {
        $platform = CompanyEmailProfileResolver::resolveForPlatform();

        return new ClientMailBrandingProfile(
            clientSlug: $slug ?? '',
            companyName: $platform->name,
            mailFromName: $platform->mail_from_name,
            replyToEmail: $platform->reply_to_email,
            supportEmail: $platform->support_email,
            logoUrl: $platform->logo_url,
        );
    }

    private static function normalizeSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        $slug = strtolower(trim($slug));

        return $slug !== '' ? $slug : null;
    }

    private static function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
