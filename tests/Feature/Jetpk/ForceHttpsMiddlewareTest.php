<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForceHttpsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_http_requests_redirect_to_canonical_https(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('http://localhost/')
            ->assertRedirect('https://jetpakistan.pk/')
            ->assertStatus(308);

        $this->get('http://localhost/about-us')
            ->assertRedirect('https://jetpakistan.pk/about-us')
            ->assertStatus(308);

        $this->get('http://localhost/sitemap.xml')
            ->assertRedirect('https://jetpakistan.pk/sitemap.xml')
            ->assertStatus(308);
    }

    public function test_production_http_requests_do_not_redirect_to_request_host_when_behind_proxy(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('http://localhost:3010/robots.txt')
            ->assertRedirect('https://jetpakistan.pk/robots.txt')
            ->assertStatus(308);
    }

    public function test_local_http_requests_are_not_redirected(): void
    {
        app()->detectEnvironment(fn (): string => 'local');

        $this->get('http://localhost/up')
            ->assertOk();
    }
}
