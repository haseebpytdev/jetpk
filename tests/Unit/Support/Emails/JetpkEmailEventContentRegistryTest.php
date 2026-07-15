<?php

namespace Tests\Unit\Support\Emails;

use App\Enums\OtaNotificationEvent;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailEventTypeMap;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use App\Support\Emails\JetpkEmailViewResolver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class JetpkEmailEventContentRegistryTest extends TestCase
{
    public function test_every_ota_event_has_content_definition(): void
    {
        foreach (OtaNotificationEvent::cases() as $event) {
            $this->assertNotNull(
                JetpkEmailEventContentRegistry::find($event->value),
                'Missing content definition for '.$event->value,
            );
        }
    }

    public function test_all_legacy_types_resolve_to_universal_view(): void
    {
        foreach (array_keys(JetpkEmailEventTypeMap::all()) as $type) {
            $this->assertSame(
                JetpkEmailViewResolver::UNIVERSAL_VIEW,
                JetpkEmailViewResolver::resolve($type, 'jetpk'),
            );
        }
    }

    public function test_only_one_canonical_shell_exists(): void
    {
        $this->assertTrue(View::exists(JetpkEmailEventContentRegistry::shellView()));
        $this->assertTrue(View::exists(JetpkEmailEventContentRegistry::contentView()));
    }

    public function test_renderer_produces_html_without_unresolved_placeholders(): void
    {
        Mail::fake();

        $sample = JetpkEmailSampleDataProvider::forType('booking_created');
        $result = app(JetpkEmailEventRenderer::class)->render(
            'booking_request_received',
            null,
            null,
            $sample,
            $sample,
            auditMode: true,
        );

        $this->assertStringNotContainsString('{{', $result->html);
        $this->assertStringNotContainsString('{{', $result->subject);
        $this->assertSame([], $result->unresolvedPlaceholders);
        $this->assertStringNotContainsString('Parwaaz', $result->html);
        Mail::assertNothingSent();
    }

    public function test_universal_email_render_audit_command_exits_without_mail_fake(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('jetpk:universal-email-render-audit');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('fail_count=0', $output);
        $this->assertStringContainsString('unresolved_placeholders=0', $output);
        $this->assertStringContainsString('mail_send_guard=ok', $output);
    }
}
