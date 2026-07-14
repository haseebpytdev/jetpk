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

class BookingRequestReceivedMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    public function __construct(
        public Booking $booking,
    ) {
        $this->applyModernCustomerEmail(
            app(CustomerFacingEmailRenderer::class)->bookingRequestReceived($booking)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your booking request was received — '.$this->booking->reference_code,
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }
}
