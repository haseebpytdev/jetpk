<?php

namespace App\Support\Client;

use App\Services\Client\ClientBrandingResolver;

/**
 * Applies client preview branding overrides to public/dashboard layout variables.
 *
 * Only active when is_client_preview() is true; production Agency branding is unchanged.
 */
final class ClientPreviewLayoutBranding
{
    /**
     * @param  array<string, mixed>  $footerPresentation
     * @return array{
     *     brandName: string,
     *     brandTagline: string,
     *     brandCssVariables: array<string, string>,
     *     headerLogoUrl: ?string,
     *     hasHeaderLogo: bool,
     *     faviconUrl: ?string,
     *     footerPresentation: array<string, mixed>,
     *     slimTopbar: array<string, mixed>,
     *     clientThemeMeta: array<string, mixed>
     * }
     */
    public static function apply(
        string $brandName,
        string $brandTagline,
        array $brandCssVariables,
        ?string $headerLogoUrl,
        bool $hasHeaderLogo,
        ?string $faviconUrl,
        array $footerPresentation,
        array $slimTopbar,
    ): array {
        if (! is_client_preview() && ! uses_jetpk_company_branding()) {
            return compact(
                'brandName',
                'brandTagline',
                'brandCssVariables',
                'headerLogoUrl',
                'hasHeaderLogo',
                'faviconUrl',
                'footerPresentation',
                'slimTopbar',
            ) + ['clientThemeMeta' => []];
        }

        $branding = client_branding();
        $theme = client_theme();

        $brandName = $branding->companyName();
        $footerText = trim($branding->footerText());
        $brandTagline = $footerText !== '' ? $footerText : $brandTagline;
        $brandCssVariables = self::cssVariablesFromBranding($branding);
        $headerLogoUrl = $branding->logoUrl();
        $hasHeaderLogo = $headerLogoUrl !== null;
        $faviconUrl = $branding->faviconUrl();
        $footerPresentation = self::patchFooterPresentation($footerPresentation, $branding);
        $slimTopbar = self::patchSlimTopbar($slimTopbar, $branding);

        return [
            'brandName' => $brandName,
            'brandTagline' => $brandTagline,
            'brandCssVariables' => $brandCssVariables,
            'headerLogoUrl' => $headerLogoUrl,
            'hasHeaderLogo' => $hasHeaderLogo,
            'faviconUrl' => $faviconUrl,
            'footerPresentation' => $footerPresentation,
            'slimTopbar' => $slimTopbar,
            'clientThemeMeta' => $theme->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function cssVariablesFromBranding(ClientBrandingResolver $branding): array
    {
        $primary = $branding->primaryColor();
        $primaryDark = self::darkenHex($primary);

        return [
            '--brand-primary' => $primary,
            '--brand-primary-dark' => $primaryDark,
            '--brand-secondary' => $branding->secondaryColor(),
            '--brand-accent' => $branding->accentColor(),
            '--client-primary' => $primary,
            '--ota-blue' => $primary,
            '--ota-blue-dark' => $primaryDark,
        ];
    }

    /**
     * @param  array<string, mixed>  $footerPresentation
     * @return array<string, mixed>
     */
    private static function patchFooterPresentation(array $footerPresentation, ClientBrandingResolver $branding): array
    {
        $footerPresentation['brand'] = array_merge($footerPresentation['brand'] ?? [], [
            'name' => $branding->companyName(),
            'logo_url' => $branding->logoUrl() ?? '',
            'description' => trim($branding->footerText()),
        ]);

        $footerPresentation['contact'] = array_merge($footerPresentation['contact'] ?? [], [
            'email' => $branding->email(),
            'phone' => $branding->phone(),
            'address' => $branding->address(),
            'show_email' => trim($branding->email()) !== '',
            'show_phone' => trim($branding->phone()) !== '',
        ]);

        $footerPresentation['bottom_bar'] = array_merge($footerPresentation['bottom_bar'] ?? [], [
            'copyright_name' => $branding->companyName(),
        ]);

        return $footerPresentation;
    }

    /**
     * @param  array<string, mixed>  $slimTopbar
     * @return array<string, mixed>
     */
    private static function patchSlimTopbar(array $slimTopbar, ClientBrandingResolver $branding): array
    {
        $items = [];
        $phone = trim($branding->phone());
        $email = trim($branding->email());

        if ($phone !== '') {
            $items[] = [
                'type' => 'phone',
                'icon' => 'fa-phone',
                'label' => $phone,
                'url' => 'tel:'.preg_replace('/\s+/', '', $phone),
            ];
        }

        if ($email !== '') {
            $items[] = [
                'type' => 'email',
                'icon' => 'fa-envelope-o',
                'label' => $email,
                'url' => 'mailto:'.$email,
            ];
        }

        if ($items !== []) {
            $slimTopbar['items'] = $items;
        }

        return $slimTopbar;
    }

    private static function darkenHex(string $hex, float $factor = 0.85): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#1d4ed8';
        }

        $r = max(0, min(255, (int) round(hexdec(substr($hex, 0, 2)) * $factor)));
        $g = max(0, min(255, (int) round(hexdec(substr($hex, 2, 2)) * $factor)));
        $b = max(0, min(255, (int) round(hexdec(substr($hex, 4, 2)) * $factor)));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
