<?php

namespace App\Services\Client;

use App\Support\Client\ClientProfileConfigReader;
use App\Support\Branding\PlatformBrandingResolver;

/**
 * Resolves client branding metadata at runtime (MC-6A).
 *
 * Preview and default context read DB branding when available; otherwise falls
 * back to config('ota-client') / config('ota_client') via ClientProfileConfigReader.
 */
final class ClientBrandingResolver
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientProfileConfigReader $configReader,
        private readonly ClientAssetResolver $assetResolver,
    ) {}

    public function companyName(): string
    {
        if (uses_jetpk_company_branding()) {
            return jetpk_company_branding()->companyName();
        }

        $name = $this->brandingField('company_name');
        if ($name !== '') {
            return $name;
        }

        $profile = $this->clientContext->get();
        if ($profile !== null) {
            $profileName = trim((string) $profile->name);
            if ($profileName !== '') {
                return $profileName;
            }
        }

        return $this->configBranding()['company_name'];
    }

    public function logoUrl(): ?string
    {
        if (uses_jetpk_company_branding()) {
            return jetpk_company_branding()->logoUrl();
        }

        return $this->assetResolver->logoUrl();
    }

    public function faviconUrl(): ?string
    {
        if (uses_jetpk_company_branding()) {
            return jetpk_company_branding()->faviconUrl();
        }

        return $this->assetResolver->faviconUrl();
    }

    public function heroImageUrl(): ?string
    {
        return $this->assetResolver->heroImageUrl();
    }

    public function headerLogoHeight(): int
    {
        if (uses_jetpk_company_branding()) {
            return jetpk_company_branding()->headerLogoHeight();
        }

        return PlatformBrandingResolver::DEFAULT_HEADER_LOGO_HEIGHT;
    }

    public function primaryColor(): string
    {
        return $this->brandingField('primary_color', '#0c4a6e');
    }

    public function secondaryColor(): string
    {
        return $this->brandingField('secondary_color', '#0ea5e9');
    }

    public function accentColor(): string
    {
        return $this->brandingField('accent_color', '#f59e0b');
    }

    public function phone(): string
    {
        return $this->brandingField('phone');
    }

    public function email(): string
    {
        return $this->brandingField('email');
    }

    public function address(): string
    {
        return $this->brandingField('address');
    }

    public function footerText(): string
    {
        return $this->brandingField('footer_text');
    }

    /**
     * @return array{
     *     company_name: string,
     *     logo_url: ?string,
     *     favicon_url: ?string,
     *     primary_color: string,
     *     secondary_color: string,
     *     accent_color: string,
     *     phone: string,
     *     email: string,
     *     address: string,
     *     footer_text: string
     * }
     */
    public function all(): array
    {
        return [
            'company_name' => $this->companyName(),
            'logo_url' => $this->logoUrl(),
            'favicon_url' => $this->faviconUrl(),
            'primary_color' => $this->primaryColor(),
            'secondary_color' => $this->secondaryColor(),
            'accent_color' => $this->accentColor(),
            'phone' => $this->phone(),
            'email' => $this->email(),
            'address' => $this->address(),
            'footer_text' => $this->footerText(),
        ];
    }

    private function brandingField(string $field, ?string $default = null): string
    {
        $branding = $this->clientContext->branding();
        if ($branding !== null) {
            $value = trim((string) ($branding->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $config = $this->configBranding();
        $value = trim((string) ($config[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return $default ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function configBranding(): array
    {
        if (uses_jetpk_company_branding()) {
            return jetpk_company_branding()->brandingConfig();
        }

        return $this->configReader->brandingFromConfig();
    }
}
