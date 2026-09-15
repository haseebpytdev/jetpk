<?php

namespace App\Support\Seo;

/**
 * Validates SEO canonical URLs against the JetPakistan canonical host policy.
 */
final class SeoCanonicalValidator
{
    public function canonicalHost(): string
    {
        $domain = trim((string) config('client.canonical_client.domain', 'jetpakistan.pk'));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        return strtolower($domain);
    }

    public function canonicalBaseUrl(): string
    {
        return 'https://'.$this->canonicalHost();
    }

    public function isValid(?string $canonical): bool
    {
        $canonical = trim((string) $canonical);
        if ($canonical === '') {
            return true;
        }

        if (str_starts_with($canonical, '/')) {
            return true;
        }

        if (! str_starts_with($canonical, 'https://')) {
            return false;
        }

        $host = parse_url($canonical, PHP_URL_HOST);

        return is_string($host) && strtolower($host) === $this->canonicalHost();
    }

    public function normalize(?string $canonical, ?string $fallbackPath = null): string
    {
        $canonical = trim((string) $canonical);
        if ($canonical === '' && $fallbackPath !== null) {
            $path = str_starts_with($fallbackPath, '/') ? $fallbackPath : '/'.$fallbackPath;

            return $this->canonicalBaseUrl().$path;
        }

        if ($canonical === '') {
            return '';
        }

        if (str_starts_with($canonical, '/')) {
            return $this->canonicalBaseUrl().$canonical;
        }

        if (str_starts_with($canonical, 'https://') && $this->isValid($canonical)) {
            return $canonical;
        }

        return '';
    }

    /**
     * @return list<string>
     */
    public function validationErrors(?string $canonical): array
    {
        $canonical = trim((string) $canonical);
        if ($canonical === '') {
            return [];
        }

        if ($this->isValid($canonical)) {
            return [];
        }

        if (str_starts_with($canonical, 'http://')) {
            return ['Canonical must use HTTPS.'];
        }

        $host = parse_url($canonical, PHP_URL_HOST);
        if (is_string($host) && strtolower($host) !== $this->canonicalHost()) {
            return ['Canonical host must be '.$this->canonicalHost().'.'];
        }

        return ['Canonical URL is invalid.'];
    }
}
