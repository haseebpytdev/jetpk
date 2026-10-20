<?php

namespace Tests\Unit\Support\Communication;

use App\Support\Communication\SmtpDeliveryFailureClassifier;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\TestCase;

class SmtpDeliveryFailureClassifierTest extends TestCase
{
    #[Test]
    public function classifies_permanent_550_5_1_1_recipient_reject(): void
    {
        $e = new UnexpectedResponseException(
            'Expected response code "250/251/252" but got code "550", with message "550 5.1.1 <user@example.test>: Recipient address rejected: User unknown"'
        );
        $result = (new SmtpDeliveryFailureClassifier)->classify($e);

        $this->assertSame(SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT, $result['delivery_failure_class']);
        $this->assertSame('550', $result['smtp_code']);
        $this->assertSame('5.1.1', $result['smtp_enhanced_status']);
        $this->assertFalse($result['retry_recommended']);
    }

    #[Test]
    public function classifies_transient_450_4_7_1(): void
    {
        $e = new UnexpectedResponseException(
            'Expected response code "250/251/252" but got code "450", with message "450 4.7.1 <user@example.test>: Recipient address rejected: Greylisted"'
        );
        $result = (new SmtpDeliveryFailureClassifier)->classify($e);

        $this->assertSame(SmtpDeliveryFailureClassifier::TRANSIENT, $result['delivery_failure_class']);
        $this->assertSame('450', $result['smtp_code']);
        $this->assertSame('4.7.1', $result['smtp_enhanced_status']);
        $this->assertTrue($result['retry_recommended']);
    }

    #[Test]
    public function bare_550_without_enhanced_status_is_unknown(): void
    {
        $e = new RuntimeException('SMTP upstream returned 550 for unknown reasons');
        $result = (new SmtpDeliveryFailureClassifier)->classify($e);

        $this->assertSame(SmtpDeliveryFailureClassifier::UNKNOWN, $result['delivery_failure_class']);
        $this->assertTrue($result['retry_recommended']);
    }

    #[Test]
    public function generic_runtime_exception_is_unknown(): void
    {
        $result = (new SmtpDeliveryFailureClassifier)->classify(new RuntimeException('connection reset'));

        $this->assertSame(SmtpDeliveryFailureClassifier::UNKNOWN, $result['delivery_failure_class']);
        $this->assertTrue($result['retry_recommended']);
    }
}
