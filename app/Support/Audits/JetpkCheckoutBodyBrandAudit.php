<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\File;

/**
 * Static checkout body brand isolation checks for JetPK flow audits (8E).
 *
 * Traces JetPK checkout shell @include chains and fails when Master body partials
 * are wired directly or when forbidden brand strings appear in rendered body sources.
 */
final class JetpkCheckoutBodyBrandAudit
{
    /** @var list<string> */
    private const FORBIDDEN_PATTERNS = [
        'parwaaz',
        'parwaaz travels',
        'yd travel',
        'yoursdomain',
    ];

    /** @var list<string> */
    private const MASTER_BODY_PARTIAL_PREFIX = 'frontend.booking.partials.';

    /**
     * @return array{
     *     pages: array<string, array{
     *         shell_view: string,
     *         body_brand: string,
     *         includes: list<string>,
     *         forbidden_hits: list<string>,
     *         status: string
     *     }>,
     *     fail_count: int,
     *     leak_count: int
     * }
     */
    public function run(): array
    {
        $pages = [
            'checkout_passenger' => [
                'shell' => 'resources/views/themes/frontend/jetpakistan/frontend/booking/passenger-details.blade.php',
                'body_partial' => 'themes.frontend.jetpakistan.frontend.booking.partials.passenger-details-body',
            ],
            'checkout_review' => [
                'shell' => 'resources/views/themes/frontend/jetpakistan/frontend/booking/review.blade.php',
                'body_partial' => 'themes.frontend.jetpakistan.frontend.booking.partials.review-body',
            ],
            'checkout_confirmation' => [
                'shell' => 'resources/views/themes/frontend/jetpakistan/frontend/booking/confirmation.blade.php',
                'body_partial' => 'themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body',
            ],
            'checkout_card_payment' => [
                'shell' => 'resources/views/themes/frontend/jetpakistan/frontend/booking/card-payment.blade.php',
                'body_partial' => 'themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body',
            ],
        ];

        $results = [];
        $failCount = 0;
        $leakCount = 0;

        foreach ($pages as $key => $page) {
            $shellPath = base_path($page['shell']);
            $shellContent = File::exists($shellPath) ? (string) File::get($shellPath) : '';
            $includes = $this->extractIncludes($shellContent);
            $bodyBrand = $this->classifyBodyBrand($includes, $page['body_partial']);
            $scannedContent = $this->collectIncludeContents($includes);
            $forbiddenHits = $this->findForbiddenPatterns($scannedContent);
            $brandingOverrideOk = $key === 'checkout_passenger'
                ? $this->jetpkBrandingOverridePresent($page['body_partial'])
                : true;

            $status = 'jetpk-owned';
            if ($bodyBrand === 'master') {
                $status = 'master-fallback-confirmed';
                $failCount++;
                $leakCount++;
            } elseif (! $brandingOverrideOk) {
                $status = 'master-fallback-risk';
                $failCount++;
                $leakCount++;
            } elseif ($forbiddenHits !== []) {
                $status = 'master-fallback-risk';
                $failCount++;
                $leakCount += count($forbiddenHits);
            }

            $results[$key] = [
                'shell_view' => $page['shell'],
                'body_brand' => $bodyBrand,
                'branding_override' => $brandingOverrideOk ? 'yes' : 'no',
                'includes' => $includes,
                'forbidden_hits' => $forbiddenHits,
                'status' => $status,
            ];
        }

        return [
            'pages' => $results,
            'fail_count' => $failCount,
            'leak_count' => $leakCount,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractIncludes(string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all("/@include\(\s*'([^']+)'/", $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param  list<string>  $includes
     */
    private function classifyBodyBrand(array $includes, string $expectedJetpkPartial): string
    {
        $hasJetpkBody = in_array($expectedJetpkPartial, $includes, true);
        $hasMasterBody = false;

        foreach ($includes as $include) {
            if (str_starts_with($include, self::MASTER_BODY_PARTIAL_PREFIX)
                && str_ends_with($include, '-body')) {
                $hasMasterBody = true;
                break;
            }
        }

        if ($hasMasterBody && ! $hasJetpkBody) {
            return 'master';
        }

        return $hasJetpkBody ? 'jetpk' : 'master';
    }

    /**
     * @param  list<string>  $includes
     */
    private function collectIncludeContents(array $includes): string
    {
        $chunks = [];

        foreach ($includes as $include) {
            $path = $this->viewNameToPath($include);
            if ($path === null || ! File::exists($path)) {
                continue;
            }
            $content = (string) File::get($path);
            $chunks[] = $content;
            foreach ($this->extractIncludes($content) as $nested) {
                $nestedPath = $this->viewNameToPath($nested);
                if ($nestedPath !== null && File::exists($nestedPath)) {
                    $chunks[] = (string) File::get($nestedPath);
                }
            }
        }

        return implode("\n", $chunks);
    }

    private function viewNameToPath(string $viewName): ?string
    {
        $relative = str_replace('.', '/', $viewName).'.blade.php';

        return resource_path('views/'.$relative);
    }

    /**
     * @return list<string>
     */
    private function findForbiddenPatterns(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $lower = strtolower($content);
        $hits = [];

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                $hits[] = $pattern;
            }
        }

        return array_values(array_unique($hits));
    }

    private function jetpkBrandingOverridePresent(string $jetpkPartial): bool
    {
        $path = $this->viewNameToPath($jetpkPartial);
        if ($path === null || ! File::exists($path)) {
            return false;
        }

        $content = (string) File::get($path);

        return str_contains($content, 'client_branding()')
            || str_contains($content, '$checkoutSupportAgencyName')
            || str_contains($content, '$publicAgencyContact = new');
    }
}
