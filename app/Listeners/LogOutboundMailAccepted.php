<?php

namespace App\Listeners;

use App\Support\Auth\LoginOtpMailDiagnostics;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

/**
 * Records SMTP handoff acceptance (Message-ID) without logging body/OTP/secrets.
 */
final class LogOutboundMailAccepted
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $headers = $message->getHeaders();

        $messageId = '';
        if ($headers->has('Message-ID')) {
            $messageId = (string) $headers->get('Message-ID')->getBodyAsString();
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
            'message_id' => $messageId !== '' ? $messageId : null,
            'subject' => $subject !== '' ? $subject : null,
            'from' => $from !== '' ? $from : null,
            'to_masked' => $toMasked,
            'sent_at_utc' => now()->utc()->toIso8601String(),
        ]);
    }
}
