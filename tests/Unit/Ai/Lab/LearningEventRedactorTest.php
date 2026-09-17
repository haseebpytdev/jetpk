<?php

namespace Tests\Unit\Ai\Lab;

use App\Services\Ai\Lab\LearningEventRedactor;
use PHPUnit\Framework\TestCase;

class LearningEventRedactorTest extends TestCase
{
    public function test_redacts_email_and_phone(): void
    {
        $redactor = new LearningEventRedactor;
        $event = $redactor->redact([
            'user_text' => 'Contact me at user@example.com or 03001234567',
        ]);

        $this->assertStringNotContainsString('user@example.com', $event['redacted_user_text']);
        $this->assertStringNotContainsString('03001234567', $event['redacted_user_text']);
        $this->assertTrue($redactor->assertRedacted($event));
    }
}
