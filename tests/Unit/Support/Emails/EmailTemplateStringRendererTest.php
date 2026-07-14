<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\EmailTemplateStringRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateStringRendererTest extends TestCase
{
    #[Test]
    public function it_replaces_spaced_and_unspaced_placeholders(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            '{{ brand_name }} — New booking — {{booking_reference}}',
            ['brand_name' => 'Parwaaz Travels', 'booking_reference' => 'PAR-TEST01'],
        );

        $this->assertSame('Parwaaz Travels — New booking — PAR-TEST01', $result->output);
        $this->assertFalse($result->hadUnresolved);
        $this->assertSame([], $result->unresolvedAfterFallback);
    }

    #[Test]
    public function it_strips_truly_unknown_placeholders(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            '{{ brand_name }} — {{ totally_unknown_key }}',
            ['brand_name' => 'Parwaaz Travels'],
            ['event_key' => 'booking_request_received'],
        );

        $this->assertSame('Parwaaz Travels — ', $result->output);
        $this->assertTrue($result->hadUnresolved);
        $this->assertContains('totally_unknown_key', $result->unresolvedAfterFallback);
        $this->assertStringNotContainsString('{{', $result->output);
    }

    #[Test]
    public function missing_known_fields_use_central_fallbacks(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            'Route: {{ route }} | Amount: {{ amount }} | PNR: {{ pnr }}',
            ['route' => '', 'amount' => ''],
        );

        $this->assertSame('Route: Route pending | Amount: Not available | PNR: Not assigned yet', $result->output);
        $this->assertContains('route', $result->fallbackKeysApplied);
        $this->assertContains('amount', $result->fallbackKeysApplied);
        $this->assertContains('pnr', $result->fallbackKeysApplied);
        $this->assertSame([], $result->unresolvedAfterFallback);
    }

    #[Test]
    public function missing_brand_name_uses_platform_fallback(): void
    {
        config([
            'ota_client.single_client_mode' => false,
            'ota_client.single_client_root' => false,
        ]);

        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            'Hello from {{ brand_name }}',
            [],
        );

        $this->assertStringContainsString('Travel Platform', $result->output);
        $this->assertStringNotContainsString('{{', $result->output);
        $this->assertStringNotContainsString('Parwaaz', $result->output);
    }

    #[Test]
    public function missing_supplier_status_uses_ops_fallback(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            'Supplier status: {{ supplier_status }}',
            [],
            ['audience' => 'admin'],
        );

        $this->assertSame('Supplier status: Pending / Staff review', $result->output);
    }

    #[Test]
    public function customer_audience_does_not_expose_ops_supplier_diagnostics(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            'Status: {{ supplier_status }}',
            [],
            ['audience' => 'customer'],
        );

        $this->assertSame('Status: In progress', $result->output);
        $this->assertStringNotContainsString('Staff review', $result->output);
    }

    #[Test]
    public function support_ticket_and_agent_application_fallbacks_render_safely(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            implode("\n", [
                'Ticket: {{ ticket_reference }}',
                'Subject: {{ ticket_subject }}',
                'From: {{ requester_name }} ({{ requester_email }})',
                'Applicant: {{ applicant_name }} in {{ city }}',
                'Login: {{ login_email }}',
                'Info: {{ information_required }}',
                'Reason: {{ rejection_reason }}',
            ]),
            [],
        );

        $this->assertStringNotContainsString('{{', $result->output);
        $this->assertStringContainsString('To be assigned', $result->output);
        $this->assertStringContainsString('Applicant', $result->output);
        $this->assertStringContainsString('Additional information required', $result->output);
        $this->assertSame([], $result->unresolvedAfterFallback);
    }

    #[Test]
    public function staff_review_reason_alias_resolves_review_reason_placeholder(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            'Reason: {{ review_reason }}',
            ['staff_review_reason' => 'Fare mismatch detected'],
        );

        $this->assertSame('Reason: Fare mismatch detected', $result->output);
    }

    #[Test]
    public function rendered_output_never_contains_raw_placeholder_tokens(): void
    {
        $renderer = app(EmailTemplateStringRenderer::class);

        $result = $renderer->render(
            '{{ brand_name }} — {{ booking_reference }} — {{ review_reason }} — {{ amount }}',
            [],
        );

        $this->assertDoesNotMatchRegularExpression('/\{\{\s*[\w.]+\s*\}\}/', $result->output);
    }

    #[Test]
    public function render_helper_returns_safe_string(): void
    {
        $output = EmailTemplateStringRenderer::renderEmailTemplateString(
            'Hello {{ customer_name }}',
            ['customer_name' => 'Sarah'],
        );

        $this->assertSame('Hello Sarah', $output);
        $this->assertStringNotContainsString('{{', $output);
    }
}
