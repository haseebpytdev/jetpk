<?php

namespace App\Listeners;

use App\Support\Auth\LoginOtpMailDiagnostics;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records SMTP handoff acceptance (Message-ID) without logging body/OTP/secrets.
 */
final class LogOutboundMailAccepted
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $headers = $message->getHeaders();

            $messageId = null;
            try {
                $messageId = $event->sent->getMessageId();
            } catch (Throwable) {
                $messageId = null;
            }

            if (($messageId === null || $messageId === '') && $headers->has('Message-ID')) {
                $messageId = (string) $headers->get('Message-ID')->getBodyAsString();
            }

            if (is_string($messageId)) {
                $messageId = trim($messageId, " \t\n\r\0\x0B<>");
                if ($messageId === '') {
                    $messageId = null;
                }
            } else {
                $messageId = null;
            }

            $subject = '';
            if ($headers->has('Subject')) {
                $subject = (string) $headers->get('Subject')->getBodyAsString();
            }

            $toMasked = [];
            foreach ($message->getTo() as $address) {
                $toMasked[] = LoginOtpMailDiagnostics::maskEmail((string) $address->getAddress());
            }

            $from = '';
            foreach ($message->getFrom() as $address) {
                $from = (string) $address->getAddress();
                break;
            }

            // Production LOG_LEVEL is often "warning"; use warning so SMTP acceptance is observable.
            Log::warning('Outbound mail accepted by transport.', [
                'mailer' => (string) config('mail.default', ''),
                'message_id' => $messageId,
                'subject' => $subject !== '' ? $subject : null,
                'from' => $from !== '' ? $from : null,
                'to_masked' => $toMasked,
                'sent_at_utc' => now()->utc()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Outbound mail acceptance logging failed.', [
                'exception_class' => $e::class,
                'error' => \App\Support\Security\SensitiveDataRedactor::sanitizeErrorMessage($e->getMessage()),
            ]);
        }
    }
}
