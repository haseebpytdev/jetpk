<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingUniversalNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{disk?: string, path: string, name?: string}|null  $attachment
     */
    public function __construct(
        public array $payload,
        public ?array $attachment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) ($this->payload['subject'] ?? 'Booking notification'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking.universal-notification',
            with: [
                'payload' => $this->payload,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = trim((string) ($this->attachment['path'] ?? ''));
        if ($path === '') {
            return [];
        }

        $attachment = Attachment::fromStorageDisk(
            (string) ($this->attachment['disk'] ?? 'local'),
            $path
        );

        $name = trim((string) ($this->attachment['name'] ?? ''));
        if ($name !== '') {
            $attachment = $attachment->as($name);
        }

        return [$attachment];
    }
}
