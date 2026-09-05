<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailPlainTextComposer;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetpkEmailFinalClosure07Test extends TestCase
{
    use RefreshDatabase;

    public function test_html_action_cta_extracts_button_url(): void
    {
        $html = '<table class="jetpk-btn"><tr><td><a href="https://jetpakistan.pk/admin/bookings/JPK-2026-004821">Open in admin</a></td></tr></table>';
        $cta = JetpkEmailPlainTextComposer::htmlActionCta($html);
        $this->assertNotNull($cta);
        $this->assertSame('https://jetpakistan.pk/admin/bookings/JPK-2026-004821', $cta['url']);
        $this->assertSame('Open in admin', $cta['label']);
    }

    public function test_html_action_cta_falls_back_to_footer_manage_url(): void
    {
        $html = '<a href="https://jetpakistan.pk/lookup-booking" target="_blank">Manage booking</a>';
        $cta = JetpkEmailPlainTextComposer::htmlActionCta($html);
        $this->assertNotNull($cta);
        $this->assertSame('https://jetpakistan.pk/lookup-booking', $cta['url']);
        $this->assertSame('Manage booking', $cta['label']);
    }

    public function test_agent_application_facts_are_structured_and_unique(): void
    {
        $facts = JetpkEmailPlainTextComposer::mergeAgentApplicationFacts([], [
            'agent_application' => [
                'reference' => 'APP-2026-4412',
                'applicant_name' => 'Sara Ahmed',
                'agency_name' => 'Skyline Partners',
                'email' => 'sara@example.com',
                'phone' => '+92 300 1234567',
                'city' => 'Lahore',
                'country' => 'Pakistan',
                'submitted_at' => '5 Sep 2026, 14:20',
                'status' => 'Pending review',
            ],
        ], []);
        $plain = JetpkEmailPlainTextComposer::compose([
            'title' => 'New agent application',
            'greeting' => 'Dear Administrator,',
            'message' => 'Please review this application.',
            'facts' => $facts,
            'cta_label' => 'Review application',
            'cta_url' => 'https://jetpakistan.pk/admin/agent-applications',
        ]);
        foreach (['APP-2026-4412', 'Sara Ahmed', 'Skyline Partners', 'sara@example.com', 'Lahore', 'Pakistan', 'Pending review', 'Review application'] as $needle) {
            $this->assertStringContainsString($needle, $plain);
        }
        $this->assertSame(1, substr_count($plain, 'Sara Ahmed'));
        $this->assertSame(1, substr_count($plain, 'Application ID:'));
    }

    public function test_booking_plain_text_cta_falls_back_to_html_action_url(): void
    {
        $sample = JetpkEmailSampleDataProvider::forEvent('booking_confirmed');
        $payload = [];
        foreach (['booking', 'itinerary', 'passengers', 'payment'] as $block) {
            if (isset($sample[$block]) && is_array($sample[$block])) {
                $payload[$block] = $sample[$block];
            }
        }
        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'booking_confirmed',
            runtimeVariables: array_merge($sample, ['recipient_role' => 'admin']),
            payload: $payload,
        );
        $htmlAction = JetpkEmailPlainTextComposer::htmlActionCta($result->html);
        $this->assertNotNull($htmlAction);
        $this->assertNotSame('', $htmlAction['url']);
        $this->assertStringContainsString($htmlAction['url'], $result->plainBody);
        $this->assertMatchesRegularExpression('/Manage booking|Open in admin|View booking/i', $result->plainBody);
    }

    public function test_booking_html_and_plain_cta_urls_match(): void
    {
        $sample = JetpkEmailSampleDataProvider::forEvent('booking_confirmed');
        $payload = [];
        foreach (['booking', 'itinerary', 'passengers', 'payment'] as $block) {
            if (isset($sample[$block]) && is_array($sample[$block])) {
                $payload[$block] = $sample[$block];
            }
        }
        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'booking_confirmed',
            runtimeVariables: array_merge($sample, [
                'recipient_role' => 'admin',
                'manage_booking_url' => 'https://jetpakistan.pk/lookup-booking',
            ]),
            payload: $payload,
        );
        $htmlAction = JetpkEmailPlainTextComposer::htmlActionCta($result->html);
        $this->assertNotNull($htmlAction);
        $this->assertStringContainsString($htmlAction['url'], $result->plainBody);
        $this->assertSame(1, substr_count($result->plainBody, $htmlAction['url']));
    }

    public function test_agent_application_plain_text_includes_structured_facts(): void
    {
        $sample = JetpkEmailSampleDataProvider::forEvent('agent_application_submitted');
        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'agent_application_submitted',
            runtimeVariables: array_merge($sample, ['recipient_role' => 'admin']),
            payload: ['agent_application' => $sample['agent_application']],
        );
        $plain = $result->plainBody;
        foreach (['APP-2026-4412', 'Sara Ahmed', 'Skyline Partners', 'sara@example.com', 'Lahore', 'Pakistan', 'Pending review'] as $needle) {
            $this->assertStringContainsString($needle, $plain);
        }
        $this->assertStringContainsString('Review application', $plain);
        $this->assertSame(1, substr_count($plain, 'APP-2026-4412'));
        $this->assertSame(1, substr_count($plain, 'Sara Ahmed'));
        $this->assertSame(0, preg_match('/<[^>]+>/', $plain));
    }

    public function test_agent_application_plain_text_does_not_duplicate_fields(): void
    {
        $sample = JetpkEmailSampleDataProvider::forEvent('agent_application_submitted');
        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'agent_application_submitted',
            runtimeVariables: array_merge($sample, ['recipient_role' => 'admin']),
            payload: ['agent_application' => $sample['agent_application']],
        );
        $this->assertSame(1, substr_count($result->html, 'APP-2026-4412'));
        $this->assertSame(1, substr_count($result->plainBody, 'Application ID:'));
        $this->assertSame(1, substr_count($result->plainBody, 'Applicant:'));
        $this->assertSame(1, substr_count($result->plainBody, 'Agency / company:'));
    }
}
