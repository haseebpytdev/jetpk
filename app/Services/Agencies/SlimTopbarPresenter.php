<?php

namespace App\Services\Agencies;

use App\Models\AgencySetting;

/**
 * Public slim topbar defaults and persistence in agency_settings.meta.slim_topbar.
 */
class SlimTopbarPresenter
{
    public const META_KEY = 'slim_topbar';

    /**
     * @return array<string, mixed>
     */
    public function presentForPublic(?AgencySetting $settings, array $client = [], array $brand = []): array
    {
        $stored = $this->storedPayload($settings);
        $phone = trim((string) ($settings?->support_phone ?: ($client['support_phone'] ?? ($brand['support_phone'] ?? ''))));
        $email = trim((string) ($settings?->support_email ?: ($client['support_email'] ?? ($brand['support_email'] ?? ''))));
        $whatsapp = trim((string) ($settings?->support_whatsapp ?: ($client['support_whatsapp'] ?? ($brand['support_whatsapp'] ?? ''))));

        $isEnabled = (bool) ($stored['is_enabled'] ?? true);
        $message = trim((string) ($stored['message'] ?? ''));
        $showPhone = (bool) ($stored['show_phone'] ?? true);
        $showEmail = (bool) ($stored['show_email'] ?? true);
        $showWhatsapp = (bool) ($stored['show_whatsapp'] ?? true);

        $items = [];
        if ($message !== '') {
            $items[] = [
                'type' => 'message',
                'icon' => 'fa-bullhorn',
                'label' => $message,
                'url' => null,
            ];
        }
        if ($showPhone && $phone !== '') {
            $items[] = [
                'type' => 'phone',
                'icon' => 'fa-phone',
                'label' => $phone,
                'url' => 'tel:'.preg_replace('/\s+/', '', $phone),
            ];
        }
        if ($showEmail && $email !== '') {
            $items[] = [
                'type' => 'email',
                'icon' => 'fa-envelope-o',
                'label' => $email,
                'url' => 'mailto:'.$email,
            ];
        }
        if ($showWhatsapp && $whatsapp !== '') {
            $digits = preg_replace('/\D+/', '', $whatsapp) ?? '';
            $items[] = [
                'type' => 'whatsapp',
                'icon' => 'fa-whatsapp',
                'label' => 'WhatsApp',
                'url' => $digits !== '' ? 'https://wa.me/'.$digits : null,
            ];
        }

        if ($items === []) {
            $items = $this->defaultStaticItems();
        }

        return [
            'is_enabled' => $isEnabled,
            'items' => $items,
            'css_variables' => $this->cssVariables($stored),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForAdmin(?AgencySetting $settings): array
    {
        $stored = $this->storedPayload($settings);

        return [
            'is_enabled' => (bool) ($stored['is_enabled'] ?? true),
            'message' => (string) ($stored['message'] ?? ''),
            'show_phone' => (bool) ($stored['show_phone'] ?? true),
            'show_email' => (bool) ($stored['show_email'] ?? true),
            'show_whatsapp' => (bool) ($stored['show_whatsapp'] ?? true),
            'background_color' => (string) ($stored['background_color'] ?? ''),
            'text_color' => (string) ($stored['text_color'] ?? ''),
            'accent_color' => (string) ($stored['accent_color'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildForStorage(array $input): array
    {
        return [
            'is_enabled' => filter_var($input['slim_topbar_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'message' => trim((string) ($input['slim_topbar_message'] ?? '')),
            'show_phone' => filter_var($input['slim_topbar_show_phone'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_email' => filter_var($input['slim_topbar_show_email'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_whatsapp' => filter_var($input['slim_topbar_show_whatsapp'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'background_color' => $this->normalizeHex($input['slim_topbar_background_color'] ?? null) ?? '',
            'text_color' => $this->normalizeHex($input['slim_topbar_text_color'] ?? null) ?? '',
            'accent_color' => $this->normalizeHex($input['slim_topbar_accent_color'] ?? null) ?? '',
        ];
    }

    /**
     * @return list<array{type: string, icon: string, label: string, url: string|null}>
     */
    protected function defaultStaticItems(): array
    {
        return [
            ['type' => 'static', 'icon' => 'fa-headphones', 'label' => '24/7 Support', 'url' => null],
            ['type' => 'static', 'icon' => 'fa-lock', 'label' => 'Secure booking', 'url' => null],
            ['type' => 'static', 'icon' => 'fa-whatsapp', 'label' => 'Fast response', 'url' => null],
            ['type' => 'static', 'icon' => 'fa-suitcase', 'label' => 'Flexible travel options', 'url' => null],
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, string>
     */
    protected function cssVariables(array $stored): array
    {
        $vars = [];
        $bg = $this->normalizeHex($stored['background_color'] ?? null);
        $text = $this->normalizeHex($stored['text_color'] ?? null);
        $accent = $this->normalizeHex($stored['accent_color'] ?? null);

        if ($bg !== null) {
            $vars['--ota-slim-topbar-bg'] = $bg;
        }
        if ($text !== null) {
            $vars['--ota-slim-topbar-text'] = $text;
        }
        if ($accent !== null) {
            $vars['--ota-slim-topbar-accent'] = $accent;
        }

        return $vars;
    }

    /**
     * @return array<string, mixed>
     */
    protected function storedPayload(?AgencySetting $settings): array
    {
        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $stored = $meta[self::META_KEY] ?? [];

        return is_array($stored) ? $stored : [];
    }

    protected function normalizeHex(mixed $hex): ?string
    {
        if (! is_string($hex)) {
            return null;
        }

        $hex = trim($hex);
        if ($hex === '') {
            return null;
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            return null;
        }

        return strtoupper($hex);
    }
}
