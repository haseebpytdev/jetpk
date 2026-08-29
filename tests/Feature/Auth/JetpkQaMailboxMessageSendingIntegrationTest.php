<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Qa\JetpkQaMailbox;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FailingMailTransport;
use Tests\TestCase;

/**
 * Proves real MessageSending → CaptureJetpkQaMailboxMessage interception chain.
 */
class JetpkQaMailboxMessageSendingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        JetpkQaMailbox::destroyStorage();
        parent::tearDown();
    }

    public function test_verify_email_notification_is_captured_via_message_sending_and_skips_smtp(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->installFailingMailer();

        $qa = 'jp-final-qa-'.bin2hex(random_bytes(4)).'@example.invalid';
        config([
            'jetpk_qa_mail.enabled' => true,
            'jetpk_qa_mail.recipient' => $qa,
        ]);

        $customer = User::factory()->customer()->create([
            'email' => $qa,
            'email_verified_at' => null,
        ]);

        // Failing SMTP must NOT throw: MessageSending listener cancels after capture.
        $customer->notify(new VerifyEmail);

        $row = JetpkQaMailbox::latest($qa);
        $this->assertNotNull($row, 'QA mailbox row must be created via MessageSending capture');
        $this->assertSame($qa, $row['recipient']);
        $this->assertNotEmpty($row['subject'] ?? null);
        $this->assertTrue(
            (is_string($row['html_body'] ?? null) && $row['html_body'] !== '')
            || (is_string($row['text_body'] ?? null) && $row['text_body'] !== ''),
            'Captured message must include a body'
        );
        $this->assertNotNull($row['verification_url'] ?? null);
        $this->assertStringContainsString('/verify-email/', (string) $row['verification_url']);
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_non_qa_recipient_is_not_captured_and_falls_through_to_mail_transport(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->installFailingMailer();

        $qa = 'jp-final-qa-'.bin2hex(random_bytes(4)).'@example.invalid';
        config([
            'jetpk_qa_mail.enabled' => true,
            'jetpk_qa_mail.recipient' => $qa,
        ]);

        $customer = User::factory()->customer()->create([
            'email' => 'normal-customer-'.bin2hex(random_bytes(3)).'@example.test',
            'email_verified_at' => null,
        ]);

        $threw = false;
        try {
            $customer->notify(new VerifyEmail);
        } catch (\Throwable $e) {
            $threw = true;
            $this->assertStringContainsString('Intentional SMTP failure', $e->getMessage());
        }

        $this->assertTrue($threw, 'Non-QA recipient must reach the configured mail transport');
        $this->assertNull(JetpkQaMailbox::latest($qa));
        $this->assertNull(JetpkQaMailbox::latest($customer->email));
    }

    public function test_exact_recipient_only_rejects_multi_recipient_messages(): void
    {
        config([
            'jetpk_qa_mail.enabled' => true,
            'jetpk_qa_mail.recipient' => 'jp-final-qa-only@example.invalid',
        ]);

        $this->assertTrue(JetpkQaMailbox::shouldCapture(['jp-final-qa-only@example.invalid']));
        $this->assertFalse(JetpkQaMailbox::shouldCapture([
            'jp-final-qa-only@example.invalid',
            'other@example.com',
        ]));
    }

    private function installFailingMailer(): void
    {
        Mail::purge('failing');
        Mail::extend('failing', static fn () => new FailingMailTransport);

        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => [
                'transport' => 'failing',
            ],
        ]);
    }
}
