<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\Agency;
use App\Support\Emails\AbandonedFlightSearchEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedFlightSearchMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    public function __construct(
        public string $subjectLine,
        public string $brandName,
        public string $supportEmail,
        public string $supportPhone,
        public string $routeLabel,
        public string $tripTypeLabel,
        public string $departDate,
        public ?string $returnDate,
        public string $passengerSummary,
        public array $offers,
        public string $ctaUrl,
        public ?Agency $agency = null,
    ) {
        $this->applyModernCustomerEmail(
            app(AbandonedFlightSearchEmailRenderer::class)->render(
                agency: $agency,
                routeLabel: $routeLabel,
                tripTypeLabel: $tripTypeLabel,
                departDate: $departDate,
                returnDate: $returnDate,
                passengerSummary: $passengerSummary,
                offers: $offers,
                ctaUrl: $ctaUrl,
            )
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }
}
