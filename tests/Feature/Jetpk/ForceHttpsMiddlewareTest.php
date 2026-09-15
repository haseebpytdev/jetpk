<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForceHttpsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_http_requests_redirect_to_https(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('http://localhost/sitemap.xml')
            ->assertRedirect('https://localhost/sitemap.xml')
            ->assertStatus(308);
    }

    public function test_local_http_requests_are_not_redirected(): void
    {
        app()->detectEnvironment(fn (): string => 'local');

        $this->get('http://localhost/up')
            ->assertOk();
    }
}
