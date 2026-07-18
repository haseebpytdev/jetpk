<?php

namespace App\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingRevision;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\Homepage\HomepageContentNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Resolves published (or admin draft-preview) client page content for JetPK public pages.
 */
final class ClientPageContentResolver
{
    public const PREVIEW_SESSION_KEY = 'client_page_preview';

    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientPageAssetService $assetService,
        private readonly JetpkHomepageContentValidator $homepageValidator,
        private readonly ClientPageSettingRevisionService $revisions,
    ) {}

    public function isDraftPreview(string $pageKey): bool
    {
        if (! Auth::check() || ! $this->canPreview()) {
            return false;
        }

        $preview = session(self::PREVIEW_SESSION_KEY);
        if (! is_array($preview)) {
            return false;
        }

        return ($preview['page_key'] ?? '') === $pageKey && ($preview['mode'] ?? '') === 'draft';
    }

    /**
     * @param  mixed  $default
     */
    public function section(string $pageKey, string $sectionKey, mixed $default = '', bool $allowEmpty = true): mixed
    {
        return $this->resolveField($this->contentFor($pageKey), $sectionKey, $default, $allowEmpty);
    }

    /**
     * Presence-aware field resolution for published Page Settings.
     *
     * - absent key → default
     * - present empty string (allowEmpty) → empty string (intentionally hidden)
     * - present null (nullable) → null
     * - non-empty → saved value
     * - boolean false → false
     * - present empty array → []
     *
     * @param  array<string, mixed>  $saved
     */
    public function resolveField(array $saved, string $key, mixed $default, bool $allowEmpty = true): mixed
    {
        if (! Arr::has($saved, $key)) {
            return $default;
        }

        $value = data_get($saved, $key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            $sanitized = $this->sanitizeString((string) $value);
            if ($sanitized === '' && $allowEmpty) {
                return '';
            }

            return $sanitized !== '' ? $sanitized : ($allowEmpty ? '' : $default);
        }

        return $value;
    }

    public function fieldIsPresent(string $pageKey, string $sectionKey): bool
    {
        return Arr::has($this->contentFor($pageKey), $sectionKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function contentFor(string $pageKey): array
    {
        if (! Schema::hasTable('client_page_settings')) {
            return [];
        }

        $profile = $this->clientContext->get();
        if ($profile === null) {
            return [];
        }

        $status = $this->isDraftPreview($pageKey)
            ? ClientPageSettingStatus::Draft
            : ClientPageSettingStatus::Published;

        $row = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', $status)
            ->first();

        if ($row === null && $status === ClientPageSettingStatus::Draft) {
            $row = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Published)
                ->first();
        }

        $content = is_array($row?->content_json) ? $row->content_json : [];

        if ($pageKey === ClientPageKeys::HOME && $content !== []) {
            $content = app(HomepageContentNormalizer::class)->normalize($content)['content'];
        }

        return $content;
    }

    public function assetUrl(string $pageKey, string $assetKey, ?string $default = null): ?string
    {
        $content = $this->contentFor($pageKey);
        $removedKey = '_media_removed.'.$assetKey;
        if (Arr::has($content, $removedKey) && (bool) data_get($content, $removedKey)) {
            return null;
        }

        $profile = $this->clientContext->get();
        if ($profile === null) {
            return $default;
        }

        return $this->assetService->urlFor($profile, $pageKey, $assetKey) ?? $default;
    }

    public function markMediaRemoved(ClientProfile $profile, string $pageKey, string $assetKey, ?int $userId = null): void
    {
        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $content = is_array($draft?->content_json) ? $draft->content_json : [];
        data_set($content, '_media_removed.'.$assetKey, true);
        $this->saveDraft($profile, $pageKey, $content, $userId);
    }

    public function beginDraftPreview(string $pageKey): void
    {
        session([
            self::PREVIEW_SESSION_KEY => [
                'page_key' => $pageKey,
                'mode' => 'draft',
            ],
        ]);
    }

    public function clearDraftPreview(): void
    {
        session()->forget(self::PREVIEW_SESSION_KEY);
    }

    public function saveDraft(ClientProfile $profile, string $pageKey, array $content, ?int $userId = null): ClientPageSetting
    {
        if (! Schema::hasTable('client_page_settings')) {
            throw new \RuntimeException('Page builder tables are not migrated yet.');
        }

        $attributes = [
            'client_profile_id' => $profile->id,
            'page_key' => $pageKey,
            'status' => ClientPageSettingStatus::Draft,
        ];

        $existing = ClientPageSetting::query()->where($attributes)->first();

        return ClientPageSetting::query()->updateOrCreate(
            $attributes,
            [
                'content_json' => $content,
                'updated_by' => $userId,
                'created_by' => $existing?->created_by ?? $userId,
            ],
        );
    }

    /**
     * @throws ValidationException
     */
    public function publish(ClientProfile $profile, string $pageKey, ?int $userId = null): ?ClientPageSetting
    {
        if (! Schema::hasTable('client_page_settings')) {
            return null;
        }

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        if ($draft === null) {
            return null;
        }

        $validatedContent = is_array($draft->content_json)
            ? $this->homepageValidator->validateAndNormalize($pageKey, $draft->content_json)
            : [];

        if ($pageKey === ClientPageKeys::HOME) {
            $validatedContent = $this->syncHomepageSupportCtaBackgroundMode($profile, $validatedContent);
        }

        $published = $this->promoteToPublished(
            $profile,
            $pageKey,
            $draft,
            $validatedContent,
            $userId,
            ClientPageSettingRevision::REASON_BEFORE_PUBLISH,
        );

        if ($pageKey === ClientPageKeys::HOME) {
            $this->assetService->publishAllForPage($profile, $pageKey);
        }

        return $published;
    }

    public function promoteToPublished(
        ClientProfile $profile,
        string $pageKey,
        ClientPageSetting $source,
        array $validatedContent,
        ?int $userId,
        string $revisionReason,
    ): ClientPageSetting {
        return DB::transaction(function () use ($profile, $pageKey, $source, $validatedContent, $userId, $revisionReason): ClientPageSetting {
            $existingPublished = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Published)
                ->first();

            if ($existingPublished !== null) {
                $this->revisions->snapshot($existingPublished, $revisionReason, $userId);
            }

            return ClientPageSetting::query()->updateOrCreate(
                [
                    'client_profile_id' => $profile->id,
                    'page_key' => $pageKey,
                    'status' => ClientPageSettingStatus::Published,
                ],
                [
                    'title' => $source->title,
                    'seo_title' => $source->seo_title,
                    'seo_description' => $source->seo_description,
                    'content_json' => $validatedContent,
                    'settings_json' => $source->settings_json,
                    'published_at' => now(),
                    'updated_by' => $userId,
                    'created_by' => $source->created_by ?? $userId,
                ],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultHomeContent(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now boarding · Pakistan',
                'headline' => 'Every flight from Pakistan,',
                'headline_highlight' => 'one honest fare.',
                'subtitle' => 'Compare 400+ airlines, pay in rupees, and get your e-ticket in seconds. The price you see is the price you pay — no surprises at checkout.',
                'search_visible' => '1',
            ],
            'trust_chips' => [
                ['label' => 'IATA accredited'],
                ['label' => 'PCAA licensed'],
                ['label' => 'Instant e-ticket'],
                ['label' => 'Lowest PKR fares'],
            ],
            'feature_board' => [
                'enabled' => '1',
                'items' => [
                    ['value' => '400+', 'label' => 'Airlines'],
                    ['value' => 'Best', 'label' => 'PKR fares'],
                    ['value' => 'Instant', 'label' => 'e-ticket'],
                    ['value' => 'IATA', 'label' => 'accredited'],
                    ['value' => 'PCAA', 'label' => 'licensed'],
                ],
            ],
            'why_book' => [
                'eyebrow' => 'The JetPakistan difference',
                'title' => 'Built for how Pakistan books.',
                'subtitle' => 'Four things we got obsessive about, so your next trip starts without friction.',
                'cards' => [
                    ['num' => '01 · Pricing', 'title' => 'True PKR pricing', 'text' => 'Fares converted and locked in rupees — no FX shock between search and payment.'],
                    ['num' => '02 · Speed', 'title' => 'Seconds to ticket', 'text' => 'Confirmed PNR and e-ticket delivered to email and WhatsApp the moment you pay.'],
                    ['num' => '03 · Choice', 'title' => '400+ airlines', 'text' => 'Local carriers and global alliances side by side, ranked by real total cost.'],
                    ['num' => '04 · Trust', 'title' => 'Licensed & secure', 'text' => 'IATA accredited, PCAA licensed, PCI-DSS payments. Your booking is protected end to end.'],
                ],
            ],
            'groups' => [
                'enabled' => '1',
                'title' => 'Group & series fares',
                'subtitle' => 'Charter blocks, hajj/umrah groups, and corporate series — managed inventory with manual approval.',
                'cta_text' => 'Browse group inventory',
                'cta_url' => '/group-ticketing',
            ],
            'support_cta' => [
                'enabled' => '1',
                'eyebrow' => 'We pick up',
                'title' => 'Stuck mid-booking? Talk to a human.',
                'subtitle' => 'Our Pakistan-based travel desk handles changes, refunds and group quotes — 24 hours a day, in Urdu or English.',
                'phone_label' => 'Call support',
                'phone_value' => '',
                'call_enabled' => '1',
                'call_label' => 'Call support',
                'call_url' => '',
                'cta_label' => 'Live chat',
                'cta_link' => '/support',
                'chat_enabled' => '1',
                'chat_label' => 'Live chat',
                'chat_url' => '/support',
                'background_mode' => 'gradient',
                'overlay_strength' => 'medium',
                'text_alignment' => 'left',
            ],
            'destinations' => [
                'enabled' => '1',
                'eyebrow' => 'Worth the trip',
                'title' => 'Destinations on the rise.',
                'subtitle' => 'Hand-picked routes Pakistan travellers book most.',
                'items' => [
                    ['id' => 'seed-dxb', 'code' => 'DXB', 'title' => 'Dubai', 'text' => 'Daily departures', 'enabled' => '1', 'sort_order' => 0, 'manual_fallback_price' => 42500],
                    ['id' => 'seed-jed', 'code' => 'JED', 'title' => 'Jeddah', 'text' => 'Umrah & Hajj routes', 'enabled' => '1', 'sort_order' => 1, 'manual_fallback_price' => 68900],
                    ['id' => 'seed-lhr', 'code' => 'LHR', 'title' => 'London', 'text' => 'UK family travel', 'enabled' => '1', 'sort_order' => 2, 'manual_fallback_price' => 198000],
                    ['id' => 'seed-ist', 'code' => 'IST', 'title' => 'Istanbul', 'text' => 'Europe connections', 'enabled' => '1', 'sort_order' => 3, 'manual_fallback_price' => 85000],
                ],
            ],
            'routes' => [
                'enabled' => '1',
                'eyebrow' => 'Trending routes',
                'title' => 'Where Pakistan is flying.',
                'subtitle' => '',
                'items' => [
                    ['id' => 'seed-khi-dxb', 'from' => 'KHI', 'to' => 'DXB', 'enabled' => '1', 'sort_order' => 0, 'trip_type' => 'one_way', 'dynamic_fare_enabled' => '1', 'manual_fallback_price' => 42500],
                    ['id' => 'seed-lhe-jed', 'from' => 'LHE', 'to' => 'JED', 'enabled' => '1', 'sort_order' => 1, 'trip_type' => 'one_way', 'dynamic_fare_enabled' => '1', 'manual_fallback_price' => 68900],
                    ['id' => 'seed-isb-lhr', 'from' => 'ISB', 'to' => 'LHR', 'enabled' => '1', 'sort_order' => 2, 'trip_type' => 'one_way', 'dynamic_fare_enabled' => '1', 'manual_fallback_price' => 198000],
                    ['id' => 'seed-khi-ruh', 'from' => 'KHI', 'to' => 'RUH', 'enabled' => '1', 'sort_order' => 3, 'trip_type' => 'one_way', 'dynamic_fare_enabled' => '1', 'manual_fallback_price' => 72000],
                ],
            ],
            'trust' => [
                'enabled' => '1',
                'eyebrow' => 'Why travellers stay',
                'title' => 'Booking that respects your time and money.',
                'subtitle' => 'No hidden markups, no chasing call centres. Every part of the journey is built to be clear and quick.',
                'cards' => [],
            ],
            'group_cards' => [
                'enabled' => '1',
                'eyebrow' => 'Curated journeys',
                'title' => 'Group travel packages.',
                'subtitle' => 'Charter blocks, hajj/umrah groups, and corporate series — managed inventory with manual approval.',
                'cta_text' => 'Browse group inventory',
                'cta_url' => '/group-ticketing',
                'items' => [],
            ],
            'featured_deals' => [
                'enabled' => '1',
                'eyebrow' => 'Editorial picks',
                'title' => 'Featured deals',
                'subtitle' => 'Hand-picked sample fares for inspiration — prices shown are editorial examples, not live quotes.',
                'cta_text' => '',
                'cta_url' => '',
                'card_count' => 3,
                'items' => [
                    ['airline' => 'PIA', 'from' => 'KHI', 'to' => 'DXB', 'depart' => '08:40', 'arrive' => '11:15', 'dur' => '2h 35m', 'stops' => 0, 'price' => 96500, 'enabled' => '1', 'sort_order' => 0],
                    ['airline' => 'AirBlue', 'from' => 'LHE', 'to' => 'IST', 'depart' => '14:10', 'arrive' => '20:30', 'dur' => '6h 20m', 'stops' => 1, 'price' => 142300, 'enabled' => '1', 'sort_order' => 1],
                    ['airline' => 'AirSial', 'from' => 'ISB', 'to' => 'JED', 'depart' => '23:55', 'arrive' => '02:45', 'dur' => '2h 50m', 'stops' => 0, 'price' => 118900, 'enabled' => '1', 'sort_order' => 2],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFooterContent(): array
    {
        return [
            'description' => [
                'text' => 'JetPakistan is a Pakistan-focused online travel platform for domestic and international flights, with transparent PKR pricing and licensed operations.',
            ],
            'legal' => [
                'copyright' => '© '.date('Y').' JetPakistan. All rights reserved.',
                'company_line' => 'JetPakistan — IATA accredited travel services.',
            ],
            'social' => [
                ['platform' => 'Facebook', 'url' => 'https://facebook.com/jetpakistan'],
                ['platform' => 'Instagram', 'url' => 'https://instagram.com/jetpakistan'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultGlobalContent(): array
    {
        return [
            'announcement' => [
                'enabled' => '0',
                'text' => '',
                'link' => '',
                'style' => 'info',
            ],
            'header_support' => [
                'phone' => '+92 21 111 000 000',
                'email' => 'ota@jetpakistan.pk',
                'hours' => 'Sun–Sat, 9:00–21:00 PKT',
            ],
            'seo' => [
                'title' => 'JetPakistan — Cheap flights & online booking',
                'description' => 'Compare airlines, book domestic and international flights in PKR, and get instant e-tickets with JetPakistan.',
                'og_image' => '',
            ],
        ];
    }

    private function canPreview(): bool
    {
        $user = Auth::user();

        return $user !== null && method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();
    }

    private function sanitizeString(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(strip_tags($decoded));
    }

    /**
     * When a Support CTA banner asset exists but background_mode stayed at gradient,
     * promote the published mode so the uploaded image renders publicly.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function syncHomepageSupportCtaBackgroundMode(ClientProfile $profile, array $content): array
    {
        if (! Schema::hasTable('client_page_assets')) {
            return $content;
        }

        $support = is_array($content['support_cta'] ?? null) ? $content['support_cta'] : [];
        $mode = (string) ($support['background_mode'] ?? 'gradient');
        if ($mode !== 'gradient') {
            return $content;
        }

        $hasDesktop = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', 'support_cta_background')
            ->where('path', '!=', '')
            ->exists();

        if (! $hasDesktop) {
            return $content;
        }

        $support['background_mode'] = 'uploaded_overlay';
        $content['support_cta'] = $support;

        return $content;
    }
}
