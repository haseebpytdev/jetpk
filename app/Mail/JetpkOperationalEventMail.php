<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * JetPK universal-shell operational email (queue-compatible).
 */
class JetpkOperationalEventMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, mime: string, content: string}>  $attachmentPayloads
     */
    public function __construct(
        public string $htmlBody,
        public string $emailSubject,
        public string $plainBody,
        public array $attachmentPayloads = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
            text: 'emails.themes.jetpakistan.plain-text',
            with: [
                'plainBody' => $this->plainBody,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $attachment): Attachment => Attachment::fromData(
                fn (): string => $attachment['content'],
                $attachment['name'],
            )->withMime($attachment['mime']),
            $this->attachmentPayloads,
        );
    }
}
