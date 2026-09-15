<?php

namespace Tests\Unit\Services\Seo;

use App\Support\Seo\SeoCanonicalValidator;
use Tests\TestCase;

class SeoCanonicalValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['client.canonical_client.domain' => 'jetpakistan.pk']);
    }

    public function test_accepts_relative_and_canonical_host_urls(): void
    {
        $validator = app(SeoCanonicalValidator::class);

        $this->assertTrue($validator->isValid('/about-us'));
        $this->assertTrue($validator->isValid('https://jetpakistan.pk/about-us'));
        $this->assertFalse($validator->isValid('https://evil.example/about-us'));
        $this->assertFalse($validator->isValid('http://jetpakistan.pk/about-us'));
    }

    public function test_normalizes_relative_paths_to_canonical_base(): void
    {
        $validator = app(SeoCanonicalValidator::class);

        $this->assertSame('https://jetpakistan.pk/faq', $validator->normalize('/faq'));
    }
}
