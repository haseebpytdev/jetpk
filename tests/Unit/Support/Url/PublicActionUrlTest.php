<?php

namespace Tests\Unit\Support\Url;

use App\Support\Url\PublicActionUrl;
use App\Support\Url\PublicUrlOriginPolicy;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicActionUrlTest extends TestCase
{
    #[Test]
    public function route_uses_configured_app_url_not_request_host(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);
        Route::get('/admin/bookings/{booking}', fn () => 'ok')->name('admin.bookings.show');

        $this->withServerVariables([
            'HTTP_HOST' => '127.0.0.1:8088',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => '8088',
        ]);

        $url = PublicActionUrl::route('admin.bookings.show', ['booking' => 34], absolute: true);

        $this->assertSame('https://jetpakistan.pk/admin/bookings/34', $url);
        $this->assertStringNotContainsString('127.0.0.1', $url);
        $this->assertStringNotContainsString(':8088', $url);
        $this->assertStringNotContainsString('/index.php/', $url);
    }

    #[Test]
    public function sanitize_rewrites_unsafe_absolute_urls_to_canonical_origin(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);

        $sanitized = PublicActionUrl::sanitize('https://127.0.0.1:8088/index.php/admin/bookings/34');

        $this->assertSame('https://jetpakistan.pk/admin/bookings/34', $sanitized);
    }

    #[Test]
    public function origin_policy_flags_private_hosts(): void
    {
        $this->assertTrue(PublicUrlOriginPolicy::isUnsafeHost('127.0.0.1'));
        $this->assertTrue(PublicUrlOriginPolicy::isUnsafeHost('localhost'));
        $this->assertTrue(PublicUrlOriginPolicy::isUnsafeHost('10.0.0.5'));
        $this->assertTrue(PublicUrlOriginPolicy::isUnsafeHost('192.168.1.20'));
        $this->assertTrue(PublicUrlOriginPolicy::isUnsafeHost('internal.example.internal'));
        $this->assertFalse(PublicUrlOriginPolicy::isUnsafeHost('jetpakistan.pk'));
    }
}
