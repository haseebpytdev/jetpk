<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\BookingPayment;
use App\Support\Emails\CustomerFacingEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRejectedMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    public function __construct(
        public BookingPayment $payment,
    ) {
        $this->applyModernCustomerEmail(
            app(CustomerFacingEmailRenderer::class)->paymentRejected($payment)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment update - '.$this->payment->booking->booking_reference
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }
}
