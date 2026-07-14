<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\Booking;
use App\Support\Emails\CustomerFacingEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingItineraryReadyMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $staffNote = null,
        public ?string $attachmentStoragePath = null,
    ) {
        $hasPdf = trim((string) ($attachmentStoragePath ?? '')) !== '';
        $this->applyModernCustomerEmail(
            app(CustomerFacingEmailRenderer::class)->itineraryReady($booking, $staffNote, $hasPdf)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ticket itinerary is ready — Booking '.$this->booking->reference_code,
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = trim((string) ($this->attachmentStoragePath ?? ''));
        if ($path === '') {
            return [];
        }

        $filename = 'ticket-itinerary-'.$this->booking->reference_code.'.pdf';

        return [
            Attachment::fromStorageDisk('local', $path)->as($filename),
        ];
    }
}
