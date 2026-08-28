<?php

namespace Tests\Feature\Auth;

use App\Support\Qa\JetpkQaMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class JetpkQaMailboxSinkTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        JetpkQaMailbox::destroyStorage();
        parent::tearDown();
    }

    public function test_exact_qa_recipient_is_captured_and_not_sent_over_smtp(): void
    {
        $qa = 'jp-final-qa-'.bin2hex(random_bytes(4)).'@example.invalid';
        config([
            'jetpk_qa_mail.enabled' => true,
            'jetpk_qa_mail.recipient' => $qa,
        ]);

        $email = (new Email)
            ->to($qa)
            ->from('noreply@jetpakistan.pk')
            ->subject('JetPakistan — Verify your email')
            ->html('<p><a href="https://jetpakistan.pk/verify-email/1/abc?expires=1&signature=sigvalue">Verify</a></p>');

        JetpkQaMailbox::captureEmail($email);
        $row = JetpkQaMailbox::latest($qa);

        $this->assertNotNull($row);
        $this->assertSame($qa, $row['recipient']);
        $this->assertNotNull($row['verification_url']);
        $this->assertStringContainsString('/verify-email/', (string) $row['verification_url']);
    }

    public function test_non_qa_recipient_is_not_captured_by_should_capture(): void
    {
        config([
            'jetpk_qa_mail.enabled' => true,
            'jetpk_qa_mail.recipient' => 'jp-final-qa-only@example.invalid',
        ]);

        $this->assertFalse(JetpkQaMailbox::shouldCapture(['customer@example.com']));
        $this->assertTrue(JetpkQaMailbox::shouldCapture(['jp-final-qa-only@example.invalid']));
        $this->assertFalse(JetpkQaMailbox::shouldCapture([
            'jp-final-qa-only@example.invalid',
            'other@example.com',
        ]));
    }
}
