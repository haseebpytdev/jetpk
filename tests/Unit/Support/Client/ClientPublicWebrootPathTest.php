<?php

namespace Tests\Unit\Support\Client;

use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClientPublicWebrootPathTest extends TestCase
{
    public function test_falls_back_to_public_path_when_configured_path_missing(): void
    {
        config(['ota_client.public_webroot_path' => base_path('nonexistent-webroot-'.uniqid())]);

        $this->assertStringNotContainsString('ota.haseebasif.com', (string) config('ota_client.public_webroot_path'));

        $this->assertSame(
            rtrim(str_replace('\\', '/', public_path()), '/'),
            ClientPublicWebrootPath::resolve(),
        );
        $this->assertFalse(ClientPublicWebrootPath::usingConfiguredPath());
    }

    public function test_uses_configured_path_when_directory_exists(): void
    {
        $dir = storage_path('app/test-webroot-'.uniqid());
        File::ensureDirectoryExists($dir);

        try {
            config(['ota_client.public_webroot_path' => $dir]);

            $this->assertTrue(ClientPublicWebrootPath::configuredExists());
            $this->assertSame(
                rtrim(str_replace('\\', '/', $dir), '/'),
                ClientPublicWebrootPath::resolve(),
            );
            $this->assertTrue(ClientPublicWebrootPath::isDirectory(''));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_path_joins_relative_segments(): void
    {
        config(['ota_client.public_webroot_path' => public_path()]);

        $this->assertSame(
            ClientPublicWebrootPath::resolve().'/client-assets/jetpk-assets',
            ClientPublicWebrootPath::path('client-assets/jetpk-assets'),
        );
    }

    public function test_public_relative_path_strips_public_prefix(): void
    {
        config(['ota_client.public_webroot_path' => public_path()]);

        $this->assertSame(
            ClientPublicWebrootPath::path('themes/frontend/jetpakistan/js/results.js'),
            ClientPublicWebrootPath::publicRelativePath('public/themes/frontend/jetpakistan/js/results.js'),
        );
    }

    public function test_audit_context_reports_both_roots(): void
    {
        $ctx = ClientPublicWebrootPath::auditContext();

        $this->assertArrayHasKey('configured_public_webroot', $ctx);
        $this->assertArrayHasKey('laravel_public_path', $ctx);
        $this->assertArrayHasKey('resolved_asset_root', $ctx);
        $this->assertSame(ClientPublicWebrootPath::laravelPublicRoot(), $ctx['laravel_public_path']);
    }
}
