<?php

namespace Tests\Support;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Intentionally failing mail transport for graceful-failure regression tests.
 */
final class FailingMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        throw new \RuntimeException('Intentional SMTP failure for JP-FINAL-CLOSURE-01 mail robustness tests.');
    }

    public function __toString(): string
    {
        return 'failing';
    }
}
