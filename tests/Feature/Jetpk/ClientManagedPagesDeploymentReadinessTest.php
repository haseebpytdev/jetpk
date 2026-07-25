<?php

namespace Tests\Feature\Jetpk;

use App\Support\Client\ClientManagedPageHardcodeAllowlist;
use App\Support\Client\ClientManagedPageReservedSlugs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class ClientManagedPagesDeploymentReadinessTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    /** @var list<string> */
    private const APPROVED_RUNTIME_FILES = [
        'app/Http/Controllers/Frontend/ClientManagedPageController.php',
        'app/Support/Client/ClientManagedPageHardcodeAllowlist.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['client_route_parity.enabled' => false]);
        $this->makeJetpkProfile();
    }

    public function test_approved_client_managed_page_runtime_files_exist(): void
    {
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            $this->assertFileExists(base_path($path));
        }
    }

    public function test_reserved_slugs_block_catch_all_conflicts(): void
    {
        foreach (['admin', 'login', 'customer', 'agent', 'staff', 'api', 'booking'] as $slug) {
            $this->assertTrue(ClientManagedPageReservedSlugs::isReserved($slug), "Expected reserved: {$slug}");
        }
    }

    public function test_missing_custom_page_returns_404(): void
    {
        $this->get('/nonexistent-custom-page-slug')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_hardcode_allowlist_forbids_cross_client_contact_patterns(): void
    {
        $patterns = ClientManagedPageHardcodeAllowlist::forbiddenContactPatterns();
        $this->assertNotEmpty($patterns);
        $this->assertContains('ota@jetpakistan.pk', $patterns);
    }

    public function test_managed_page_controller_registers_faq_terms_privacy_routes(): void
    {
        $this->assertTrue(Route::has('faq'));
        $this->assertTrue(Route::has('terms'));
        $this->assertTrue(Route::has('privacy'));
    }
}
