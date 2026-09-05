<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\EmailContextualCtaResolver;
use App\Support\Emails\EmailRecipientRoleGreeting;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetpkEmailFinalClosure06Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_support_cta_uses_admin_tickets_index_not_homepage(): void
    {
        $cta = EmailContextualCtaResolver::resolve('support_ticket_created', 'admin', [
            'ticket_reference' => 'TKT-88213',
            'support_url' => 'https://jetpakistan.pk',
            'manage_booking_url' => 'https://jetpakistan.pk/lookup-booking',
        ]);
        $this->assertNotNull($cta);
        $this->assertSame('Open support ticket', $cta['label']);
        $this->assertStringContainsString('/admin/support/tickets', $cta['url']);
        $this->assertStringContainsString('TKT-88213', $cta['url']);
        $this->assertStringNotContainsString('lookup-booking', $cta['url']);
        $this->assertNotSame('https://jetpakistan.pk', rtrim($cta['url'], '/'));
    }

    public function test_support_html_omits_unrelated_manage_booking(): void
    {
        $html = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'support_ticket_created',
            runtimeVariables: array_merge(JetpkEmailSampleDataProvider::forEvent('support_ticket_created'), [
                'recipient_role' => 'admin',
            ]),
            payload: [],
        )->html;
        $this->assertStringNotContainsString('Manage booking', $html);
        $this->assertStringNotContainsString('lookup-booking', $html);
        $this->assertStringContainsString('Open support ticket', $html);
    }

    public function test_same_booking_fixture_shares_airline_flight_and_pnr(): void
    {
        $confirmed = JetpkEmailSampleDataProvider::forEvent('booking_confirmed');
        $ticketed = JetpkEmailSampleDataProvider::forEvent('ticket_issued');
        $this->assertSame($confirmed['booking_reference'] ?? $confirmed['booking']['reference'], $ticketed['booking_reference'] ?? $ticketed['booking']['reference']);
        $this->assertSame($confirmed['itinerary'][0]['airline'], $ticketed['itinerary'][0]['airline']);
        $this->assertSame($confirmed['itinerary'][0]['flight_no'], $ticketed['itinerary'][0]['flight_no']);
        $this->assertSame($confirmed['itinerary'][0]['from'], $ticketed['itinerary'][0]['from']);
        $this->assertSame($confirmed['itinerary'][0]['to'], $ticketed['itinerary'][0]['to']);
        $this->assertSame($confirmed['booking']['pnr'] ?? $confirmed['pnr'], $ticketed['booking']['pnr'] ?? $ticketed['pnr']);
        $this->assertSame('Pakistan International', $confirmed['itinerary'][0]['airline']);
        $this->assertSame('PK-211', $confirmed['itinerary'][0]['flight_no']);
    }

    public function test_booking_plain_text_includes_required_fields(): void
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
        $plain = $result->plainBody;
        $this->assertSame(0, preg_match('/<[^>]+>/', $plain));
        foreach (['JPK-2026-004821', 'X7K9QP', 'KHI', 'DXB', 'PK-211', 'Pakistan International', 'Confirmed', 'Paid', '96,500'] as $needle) {
            $this->assertStringContainsString($needle, $plain);
        }
        $this->assertStringContainsString('Ayesha Khan', $plain);
        $this->assertStringContainsString('Dear Administrator', $plain);
    }

    public function test_agent_application_html_does_not_duplicate_field_block(): void
    {
        $sample = JetpkEmailSampleDataProvider::forEvent('agent_application_submitted');
        $html = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'agent_application_submitted',
            runtimeVariables: array_merge($sample, ['recipient_role' => 'admin']),
            payload: ['agent_application' => $sample['agent_application']],
        )->html;
        $this->assertSame(1, substr_count($html, 'APP-2026-4412'));
        $this->assertSame(1, substr_count($html, 'Sara Ahmed'));
        $this->assertStringContainsString('Review application', $html);
        $this->assertSame('Dear Administrator,', EmailRecipientRoleGreeting::line('admin', 'Sara Ahmed'));
    }
}
