<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guard CMS-managed public fetches stay cache: no-store (≤10s propagation).
 */
class JpAdminCms03CmsFetchFreshnessTest extends TestCase
{
    #[Test]
    public function managed_cms_page_fetches_use_no_store(): void
    {
        $files = [
            base_path('frontend/features/public-content/utils/laravel-api.ts'),
            base_path('frontend/features/public-content/services/cms-page-service.ts'),
            base_path('frontend/features/public-content/services/custom-page-service.ts'),
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
            $source = (string) file_get_contents($file);
            $this->assertStringContainsString('cache: "no-store"', $source, $file.' must use cache: no-store');
            $this->assertStringNotContainsString('revalidate: 60', $source, $file.' must not use revalidate: 60');
        }
    }
}
