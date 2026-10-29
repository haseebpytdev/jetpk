<?php

namespace App\Services\Homepage;

/**
 * Generates validated responsive hero variants from a stored CMS upload.
 *
 * Thin facade over {@see JetpkResponsiveImageOptimizer} preserving the historical
 * hero API (pageKey/optimize signature and lcp derivative directory).
 */
final class JetpkHeroImageOptimizer
{
    public function __construct(
        private readonly JetpkResponsiveImageOptimizer $responsiveOptimizer,
    ) {}

    /**
     * @return array{
     *     activated: bool,
     *     fingerprint: string,
     *     warning: ?string,
     *     manifest: ?array<string, mixed>,
     *     published_paths: list<string>,
     *     validation: list<array<string, mixed>>
     * }
     */
    public function optimize(
        string $absoluteSourcePath,
        string $profileSlug,
        string $pageKey = 'home',
        ?string $relativeSourcePath = null,
    ): array {
        return $this->responsiveOptimizer->optimize(
            $absoluteSourcePath,
            $profileSlug,
            JetpkResponsiveImageOptimizer::PROFILE_HERO,
            $pageKey,
            $relativeSourcePath,
        );
    }

    public function deleteVariantDirectory(string $profileSlug, string $pageKey, string $fingerprint): void
    {
        $this->responsiveOptimizer->deleteVariantDirectory(
            $profileSlug,
            $pageKey,
            JetpkResponsiveImageOptimizer::PROFILE_HERO,
            $fingerprint,
        );
    }
}
