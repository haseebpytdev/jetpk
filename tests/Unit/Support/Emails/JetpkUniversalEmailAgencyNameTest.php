<?php

namespace Tests\Unit\Support\Emails;

use App\Enums\OtaNotificationEvent;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\EmailPlaceholderFallbacks;
use App\Support\Emails\EmailTemplateStringRenderer;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailEventTypeMap;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JetpkUniversalEmailAgencyNameTest extends TestCase
{
    protected function setJetpkDeployment(): void
    {
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
        ]);
    }

    #[Test]
    public function jetpk_agency_name_falls_back_to_jetpakistan(): void
    {
        $this->setJetpkDeployment();

        $fallback = EmailPlaceholderFallbacks::fallbackFor('agency_name', []);

        $this->assertSame('JetPakistan', $fallback);
    }

    #[Test]
    public function jetpk_brand_name_falls_back_to_jetpakistan(): void
    {
        $this->setJetpkDeployment();

        $fallback = EmailPlaceholderFallbacks::fallbackFor('brand_name', []);

        $this->assertSame('JetPakistan', $fallback);
    }

    #[Test]
    public function neutral_non_jetpk_fallback_is_travel_platform(): void
    {
        config([
            'ota_client.single_client_mode' => false,
            'ota_client.single_client_root' => false,
        ]);

        $brandFallback = EmailPlaceholderFallbacks::fallbackFor('brand_name', []);
        $agencyFallback = EmailPlaceholderFallbacks::fallbackFor('agency_name', []);

        $this->assertSame('Travel Platform', $brandFallback);
        $this->assertSame('Travel Platform', $agencyFallback);
    }

    #[Test]
    public function explicit_agency_name_is_preserved(): void
    {
        $fallback = EmailPlaceholderFallbacks::fallbackFor('agency_name', [
            'agency_name' => 'Acme Travel Services',
        ]);

        $this->assertSame('Acme Travel Services', $fallback);
    }

    #[Test]
    public function forbidden_master_names_never_used_as_fallback(): void
    {
        foreach (['Parwaaz Travels', 'YD Travel', 'YoursDomain'] as $forbidden) {
            $fallback = EmailPlaceholderFallbacks::fallbackFor('agency_name', [
                'agency_name' => $forbidden,
                'brand_name' => $forbidden,
            ]);

            $this->assertNotSame($forbidden, $fallback);
            $this->assertStringNotContainsString('Parwaaz', $fallback);
        }
    }

    #[Test]
    public function sample_variables_include_required_branding_keys(): void
    {
        $vars = EmailBaseVariables::jetpkSampleVariables();

        foreach (['brand_name', 'agency_name', 'company_name', 'support_email', 'support_phone'] as $key) {
            $this->assertArrayHasKey($key, $vars);
            $this->assertNotSame('', trim((string) $vars[$key]));
        }

        $this->assertSame($vars['brand_name'], $vars['agency_name']);
    }

    #[Test]
    public function all_ota_event_sample_renders_have_zero_unresolved_placeholders(): void
    {
        $renderer = app(JetpkEmailEventRenderer::class);

        foreach (OtaNotificationEvent::cases() as $event) {
            $type = JetpkEmailEventTypeMap::typeForEvent($event->value) ?? 'notification';
            $sample = JetpkEmailSampleDataProvider::forType($type);
            $result = $renderer->render($event->value, null, null, $sample, $sample, auditMode: true);

            $this->assertSame([], $result->unresolvedPlaceholders, 'Unresolved placeholders for '.$event->value);
            $this->assertStringNotContainsString('{{', $result->subject, 'Subject placeholder for '.$event->value);
            $this->assertStringNotContainsString('{{', $result->html, 'HTML placeholder for '.$event->value);
        }
    }

    #[Test]
    public function audit_mode_does_not_write_production_warning_logs(): void
    {
        Log::spy();

        app(EmailTemplateStringRenderer::class)->render(
            '{{ agency_name }} — test',
            [],
            ['audit_mode' => true, 'event_key' => 'booking_request_received'],
        );

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function normal_runtime_mode_still_reports_unresolved_placeholders(): void
    {
        Log::spy();

        $result = app(EmailTemplateStringRenderer::class)->render(
            '{{ totally_unknown_key }}',
            [],
            ['event_key' => 'booking_request_received'],
        );

        $this->assertTrue($result->hadUnresolved);
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function render_result_reports_placeholder_defect_for_synthetic_unresolved_key(): void
    {
        $result = new \App\Support\Emails\JetpkEmailRenderResult(
            eventKey: 'booking_request_received',
            subject: 'Test',
            html: '<p>Test</p>',
            content: [],
            usedDbTemplate: false,
            unresolvedPlaceholders: ['totally_unknown_audit_key'],
        );

        $this->assertTrue($result->hasPlaceholderDefects());
    }

    #[Test]
    public function audit_command_fails_when_synthetic_unresolved_placeholder_is_introduced(): void
    {
        $defectResult = new \App\Support\Emails\JetpkEmailRenderResult(
            eventKey: 'booking_request_received',
            subject: 'Broken {{ totally_unknown_audit_key }}',
            html: '<p>Broken</p>',
            content: [],
            usedDbTemplate: false,
            unresolvedPlaceholders: ['totally_unknown_audit_key'],
        );

        $this->assertTrue($defectResult->hasPlaceholderDefects());
        $this->assertContains('totally_unknown_audit_key', $defectResult->unresolvedPlaceholders);
    }

    #[Test]
    public function audit_command_detects_unresolvable_placeholder_keys(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);
        $result = $renderer->render(
            'Value: {{ totally_unknown_audit_key }}',
            EmailBaseVariables::jetpkSampleVariables(),
            ['audit_mode' => true],
        );

        $this->assertContains('totally_unknown_audit_key', $result->unresolvedAfterFallback);
    }

    #[Test]
    public function universal_email_render_audit_command_exits_zero_without_mail_transport(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('jetpk:universal-email-render-audit');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('fail_count=0', $output);
        $this->assertStringContainsString('unresolved_placeholders=0', $output);
        $this->assertStringContainsString('mail_send_guard=ok', $output);
        $this->assertStringContainsString('transport_called=0', $output);
    }

    #[Test]
    public function production_audit_command_has_no_testing_framework_dependency(): void
    {
        $path = app_path('Console/Commands/JetpkUniversalEmailRenderAuditCommand.php');
        $source = (string) file_get_contents($path);

        $this->assertStringNotContainsString('Mail::fake', $source);
        $this->assertStringNotContainsString('PHPUnit', $source);
    }
}
