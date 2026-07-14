<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\Booking;
use App\Support\Emails\CustomerFacingEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingStatusChangedMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $statusLabel,
    ) {
        $this->applyModernCustomerEmail(
            app(CustomerFacingEmailRenderer::class)->bookingStatusChanged($booking, $statusLabel)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking status update - '.$this->booking->booking_reference
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }
}
