<?php

namespace App\Services\Seo;

use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;

/**
 * Resolves search-engine verification tokens: published CMS setting → env → null.
 */
final class SeoVerificationResolver
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    public function googleToken(): ?string
    {
        $fromCms = trim((string) data_get($this->verificationSettings(), 'google', ''));
        if ($fromCms !== '') {
            return $fromCms;
        }

        $fromEnv = trim((string) config('services.google.site_verification', ''));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    public function bingToken(): ?string
    {
        $fromCms = trim((string) data_get($this->verificationSettings(), 'bing', ''));
        if ($fromCms !== '') {
            return $fromCms;
        }

        $fromEnv = trim((string) config('services.bing.site_verification', ''));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    /**
     * @return array{google: string, bing: string, source: string}
     */
    public function adminView(): array
    {
        $published = $this->publishedVerificationSettings();
        $draft = $this->draftVerificationSettings();

        return [
            'google' => $draft['google'] !== '' ? $draft['google'] : $published['google'],
            'bing' => $draft['bing'] !== '' ? $draft['bing'] : $published['bing'],
            'published_google' => $published['google'],
            'published_bing' => $published['bing'],
            'effective_google' => $this->googleToken() ?? '',
            'effective_bing' => $this->bingToken() ?? '',
            'env_google' => trim((string) config('services.google.site_verification', '')),
            'env_bing' => trim((string) config('services.bing.site_verification', '')),
            'has_draft' => $draft !== ['google' => '', 'bing' => ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationSettings(): array
    {
        return $this->publishedVerificationSettings();
    }

    /**
     * @return array{google: string, bing: string}
     */
    private function publishedVerificationSettings(): array
    {
        $global = $this->contentResolver->contentFor(ClientPageKeys::GLOBAL);
        $verification = is_array($global['verification'] ?? null) ? $global['verification'] : [];

        return [
            'google' => trim((string) ($verification['google'] ?? '')),
            'bing' => trim((string) ($verification['bing'] ?? '')),
        ];
    }

    /**
     * @return array{google: string, bing: string}
     */
    private function draftVerificationSettings(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('client_page_settings')) {
            return ['google' => '', 'bing' => ''];
        }

        $profile = app(\App\Services\Client\CurrentClientContext::class)->get();
        if ($profile === null) {
            return ['google' => '', 'bing' => ''];
        }

        $draft = \App\Models\ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::GLOBAL)
            ->where('status', \App\Enums\ClientPageSettingStatus::Draft)
            ->first();

        $verification = is_array($draft?->content_json['verification'] ?? null)
            ? $draft->content_json['verification']
            : [];

        return [
            'google' => trim((string) ($verification['google'] ?? '')),
            'bing' => trim((string) ($verification['bing'] ?? '')),
        ];
    }
}
