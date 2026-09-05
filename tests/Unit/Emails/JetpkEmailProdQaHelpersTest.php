<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\EmailRecipientRoleSubjectTagger;
use App\Support\Emails\HtmlEmailPlainTextConverter;
use App\Support\Emails\JetpkEmailQaContentAuditor;
use App\Support\Emails\JetpkEmailQaCorrelation;
use App\Support\Emails\JetpkEmailQaRecipientLock;
use App\Support\Emails\JetpkEmailQaSnapshotStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class JetpkEmailProdQaHelpersTest extends TestCase
{
    public function test_customer_subject_is_untagged(): void
    {
        $subject = 'JetPakistan — Booking confirmed — JPK-2026-004821';
        $this->assertSame($subject, EmailRecipientRoleSubjectTagger::apply($subject, 'customer'));
        $this->assertSame($subject, EmailRecipientRoleSubjectTagger::apply($subject, 'user'));
    }

    public function test_role_tags_follow_intended_role_not_mailbox(): void
    {
        $base = 'JetPakistan — Booking confirmed — JPK-2026-004821';
        $this->assertSame('[ADMIN] '.$base, EmailRecipientRoleSubjectTagger::apply($base, 'admin'));
        $this->assertSame('[AGENT] '.$base, EmailRecipientRoleSubjectTagger::apply($base, 'agent_booking'));
        $this->assertSame('[STAFF] '.$base, EmailRecipientRoleSubjectTagger::apply($base, 'assigned_staff'));
        $this->assertSame('[OPS] '.$base, EmailRecipientRoleSubjectTagger::apply($base, 'finance'));
        $this->assertSame('[OPS] '.$base, EmailRecipientRoleSubjectTagger::apply($base, 'ops'));
        $this->assertSame(
            EmailRecipientRoleSubjectTagger::apply($base, 'admin'),
            EmailRecipientRoleSubjectTagger::apply($base, 'admin'),
        );
    }

    public function test_plain_text_strips_css_and_html(): void
    {
        $html = '<html><head><style>body{color:red}@media (max-width:600px){p{}}</style></head><body><p>Hello <b>JetPakistan</b></p></body></html>';
        $plain = HtmlEmailPlainTextConverter::fromHtml($html);
        $this->assertStringContainsString('Hello JetPakistan', $plain);
        $this->assertStringNotContainsString('color:red', $plain);
        $this->assertStringNotContainsString('<style', $plain);
        $this->assertStringNotContainsString('@media', $plain);
    }

    public function test_recipient_lock_fails_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JetpkEmailQaRecipientLock::activate('other@example.com');
    }

    public function test_recipient_lock_accepts_authorized_inbox_only(): void
    {
        JetpkEmailQaRecipientLock::activate('myworkhaseeb@gmail.com');
        $this->assertTrue(JetpkEmailQaRecipientLock::isActive());
        $this->assertSame('myworkhaseeb@gmail.com', JetpkEmailQaRecipientLock::enforceOrFail('myworkhaseeb@gmail.com'));
        JetpkEmailQaRecipientLock::deactivate();
        $this->assertFalse(JetpkEmailQaRecipientLock::isActive());
    }

    public function test_correlation_format_and_snapshot_sanitization(): void
    {
        $id = JetpkEmailQaCorrelation::make('booking-confirmed');
        $this->assertMatchesRegularExpression('/^JP-EMAIL-PROD-QA-02-BOOKING-CONFIRMED-\d{14}-[A-Z0-9]{6}$/', $id);

        $run = 'unit-'.uniqid();
        $store = JetpkEmailQaSnapshotStore::createForRun($run);
        $store->insert([
            'run_id' => $run,
            'scenario_id' => 's1',
            'correlation_id' => $id,
            'html_body' => 'otp: 123456 token=abcsecret',
            'plain_text_body' => 'password: hunter2',
            'subject' => 'Test',
        ]);
        $this->assertTrue($store->correlationExists($id));
        $row = $store->all()[0];
        $this->assertStringNotContainsString('hunter2', (string) $row['plain_text_body']);
        $this->assertStringNotContainsString('123456', (string) $row['html_body']);
        File::delete($store->path());
    }

    public function test_content_auditor_flags_css_in_plain_text(): void
    {
        $auditor = new JetpkEmailQaContentAuditor();
        $result = $auditor->audit(
            'JetPakistan notice',
            '<p>JetPakistan</p>',
            'body { font-family: Arial } @media screen {}',
        );
        $this->assertFalse($result['pass']);
        $this->assertContains('plain_text_contains_css', $result['failures']);
    }
}
