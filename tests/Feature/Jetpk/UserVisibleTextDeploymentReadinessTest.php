<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class UserVisibleTextDeploymentReadinessTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    /** @var list<string> */
    private const APPROVED_RUNTIME_FILES = [
        'resources/views/themes/frontend/jetpakistan/frontend/support.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/support/submitted.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/agent-registration/form.blade.php',
    ];

    /** @var list<string> */
    private const FORBIDDEN_LITERALS = [
        'Parwaaz',
        'YoursDomain',
        'YD Travel',
        'haseeb-master',
        'ota.haseebasif.com',
        '&amp;amp;',
        'â€™',
        'â€”',
        'Ã',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->makeJetpkProfile();
    }

    public function test_approved_text_runtime_files_exist(): void
    {
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            $this->assertFileExists(base_path($path), "Missing runtime file: {$path}");
        }
    }

    public function test_support_copy_uses_proper_separator_and_branding_resolver(): void
    {
        $source = (string) file_get_contents(base_path(self::APPROVED_RUNTIME_FILES[0]));
        $this->assertStringContainsString('Contact JetPakistan', $source);
        $this->assertStringContainsString('Manage booking</a>', $source);
        $this->assertStringContainsString('Travel agent partnership</a>', $source);
    }

    public function test_support_submitted_and_agent_registration_use_branding_resolver(): void
    {
        $submitted = (string) file_get_contents(base_path(self::APPROVED_RUNTIME_FILES[1]));
        $this->assertStringContainsString('client_branding()->companyName()', $submitted);

        $agentForm = (string) file_get_contents(base_path(self::APPROVED_RUNTIME_FILES[2]));
        $this->assertStringContainsString('client_branding()->companyName()', $agentForm);
    }

    public function test_approved_text_surfaces_have_no_forbidden_literals_or_mojibake(): void
    {
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            $source = (string) file_get_contents(base_path($path));
            foreach (self::FORBIDDEN_LITERALS as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$path} contains {$forbidden}");
            }
        }
    }
}
