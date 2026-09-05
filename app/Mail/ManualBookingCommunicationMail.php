<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/**
 * Manual booking-console customer email with modern layout (I8).
 */
class ManualBookingCommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $htmlBody,
        public string $emailSubject,
        public string $plainBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        $plain = $this->plainBody;
        if ($plain !== '') {
            $this->withSymfonyMessage(static function (Email $message) use ($plain): void {
                $message->text($plain);
            });
        }

        return new Content(
            htmlString: $this->htmlBody,
        );
    }
}
