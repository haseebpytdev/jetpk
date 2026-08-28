<?php

namespace App\Listeners\Mail;

use App\Support\Qa\JetpkQaMailbox;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Captures exact QA recipient messages into the ephemeral SQLite mailbox and cancels SMTP for that message only.
 */
final class CaptureJetpkQaMailboxMessage
{
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        if (! $message instanceof Email) {
            return null;
        }

        $recipients = [];
        foreach (array_merge($message->getTo(), $message->getCc(), $message->getBcc()) as $address) {
            if ($address instanceof Address) {
                $recipients[] = strtolower(trim($address->getAddress()));
            }
        }

        if (! JetpkQaMailbox::shouldCapture($recipients)) {
            return null;
        }

        JetpkQaMailbox::captureEmail($message);

        // Cancel only this send; other recipients continue on the normal mailer.
        return false;
    }
}
