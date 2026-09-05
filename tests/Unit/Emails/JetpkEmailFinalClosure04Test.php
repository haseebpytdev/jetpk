<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\EmailContextualCtaResolver;
use App\Support\Emails\EmailRecipientRoleGreeting;
use App\Support\Emails\EmailRecipientRoleSubjectTagger;
use App\Support\Emails\HtmlEmailPlainTextConverter;
use App\Support\Emails\JetpkEmailPlainTextComposer;
use Tests\TestCase;

class JetpkEmailFinalClosure04Test extends TestCase
{
    public function test_staff_greeting_ignores_customer_payload_name(): void
    {
        $this->assertSame(
            'Dear Administrator,',
            EmailRecipientRoleGreeting::line('admin', 'Ayesha Khan'),
        );
        $this->assertSame(
            'Dear Platform Administrator,',
            EmailRecipientRoleGreeting::line('platform_admin', 'Ayesha Khan'),
        );
        $this->assertSame(
            'Dear Agency Administrator,',
            EmailRecipientRoleGreeting::line('agency_admin', 'Ayesha Khan'),
        );
        $this->assertSame('Dear Staff Member,', EmailRecipientRoleGreeting::line('staff', 'Ayesha Khan'));
        $this->assertSame('Dear Agent,', EmailRecipientRoleGreeting::line('booking_agent', 'Ayesha Khan'));
        $this->assertSame('Hello Ayesha Khan,', EmailRecipientRoleGreeting::line('customer', 'Ayesha Khan'));
        $this->assertTrue(EmailRecipientRoleGreeting::isStaffFacingRole('admin'));
        $this->assertFalse(EmailRecipientRoleGreeting::isStaffFacingRole('customer'));
    }

    public function test_subject_role_tag_still_admin_for_booking_confirmed(): void
    {
        $base = 'JetPakistan — Booking confirmed — JPK-2026-004821';
        $this->assertSame('[ADMIN] '.$base, EmailRecipientRoleSubjectTagger::apply($base, 'admin'));
    }

    public function test_plain_text_composer_has_no_html_or_layout_flood(): void
    {
        $plain = JetpkEmailPlainTextComposer::compose([
            'title' => 'Ticket issued',
            'greeting' => 'Dear Agent,',
            'message' => 'Your e-tickets are confirmed.',
            'facts' => [
                ['label' => 'Booking reference', 'value' => 'JPK-2026-004821'],
                ['label' => 'PNR', 'value' => 'X7K9QP'],
            ],
            'cta_label' => 'View booking',
            'cta_url' => 'https://jetpakistan.pk/login',
            'support_email' => 'support@jetpakistan.pk',
            'footer' => '© JetPakistan',
        ]);
        $this->assertSame(0, preg_match('/<[^>]+>/', $plain));
        $this->assertSame(0, preg_match('/[{};]|@media|font-family/i', $plain));
        $this->assertStringNotContainsString("\u{200C}", $plain);
        $this->assertStringNotContainsString("\n\n\n", $plain);
        $this->assertStringContainsString('Dear Agent,', $plain);
        $this->assertStringContainsString('PNR: X7K9QP', $plain);
    }

    public function test_html_converter_fallback_still_strips_tags(): void
    {
        $plain = HtmlEmailPlainTextConverter::fromHtml('<table><tr><td>Hello</td></tr></table><style>p{}</style>');
        $this->assertStringContainsString('Hello', $plain);
        $this->assertStringNotContainsString('<td', $plain);
    }

    public function test_contextual_cta_prefers_existing_admin_routes_when_present(): void
    {
        $cta = EmailContextualCtaResolver::resolve('pnr_manual_review_digest', 'platform_admin', []);
        $this->assertNotNull($cta);
        $this->assertNotSame('', $cta['url']);
        $this->assertSame('Open admin', $cta['label']);

        $ticket = EmailContextualCtaResolver::resolve('ticket_issued', 'customer', [
            'manage_booking_url' => 'https://jetpakistan.pk/customer/bookings/JPK-2026-004821',
        ]);
        $this->assertSame('View booking', $ticket['label'] ?? null);
        $this->assertStringContainsString('/customer/bookings/', $ticket['url'] ?? '');
    }
}
