<?php

namespace Tests\Feature\Console;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OtaEmailTemplateSmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function email_template_smoke_command_passes_without_sending_mail(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);

        $exitCode = $this->artisan('ota:email-template-smoke')->run();

        $this->assertSame(0, $exitCode);
        Mail::assertNothingSent();

        $this->artisan('ota:email-template-smoke')
            ->expectsOutputToContain('total_templates_checked=')
            ->expectsOutputToContain('templates_with_unresolved_placeholders=0')
            ->expectsOutputToContain('unresolved_after_fallback_count=0')
            ->expectsOutputToContain('failed=0')
            ->assertSuccessful();
    }

    #[Test]
    public function email_template_smoke_visual_mode_passes_without_sending_mail(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);

        $this->artisan('ota:email-template-smoke', ['--visual' => true])
            ->expectsOutputToContain('visual_failed=0')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }
}
