<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\PublicMediaUrl;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicMediaUrlTest extends TestCase
{
    public function test_normalizes_same_host_absolute_url_to_path(): void
    {
        Config::set('app.url', 'https://jetpakistan.pk');

        $normalized = PublicMediaUrl::normalize(
            'https://jetpakistan.pk/storage/client-assets/jetpk-assets/pages/home/hero.png?v=1',
        );

        $this->assertSame('/storage/client-assets/jetpk-assets/pages/home/hero.png?v=1', $normalized);
    }

    public function test_strips_localhost_to_path_only(): void
    {
        $normalized = PublicMediaUrl::normalize(
            'https://127.0.0.1:8088/themes/frontend/jetpakistan/images/homepage-destination-fallback.svg',
        );

        $this->assertSame('/themes/frontend/jetpakistan/images/homepage-destination-fallback.svg', $normalized);
    }

    public function test_preserves_relative_paths(): void
    {
        $this->assertSame('/storage/logo.png', PublicMediaUrl::normalize('/storage/logo.png'));
    }

    public function test_returns_null_for_empty_values(): void
    {
        $this->assertNull(PublicMediaUrl::normalize(null));
        $this->assertNull(PublicMediaUrl::normalize(''));
        $this->assertNull(PublicMediaUrl::normalize('   '));
    }
}
