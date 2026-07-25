<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class GroupTicketingSearchDeploymentReadinessTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    private const RUNTIME_FILE = 'resources/views/themes/frontend/jetpakistan/frontend/group-ticketing/search.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->makeJetpkProfile();
    }

    public function test_group_ticketing_search_view_exists(): void
    {
        $this->assertFileExists(base_path(self::RUNTIME_FILE));
    }

    public function test_airline_sort_label_uses_readable_range_not_mojibake(): void
    {
        $source = (string) file_get_contents(base_path(self::RUNTIME_FILE));
        $this->assertStringContainsString('Airline A–Z', $source);
        $this->assertStringNotContainsString('Parwaaz', $source);
        $this->assertStringNotContainsString('â', $source);
    }

    public function test_group_search_page_renders_without_server_error(): void
    {
        $this->get('/groups/search')->assertOk();
        Http::assertNothingSent();
    }
}
