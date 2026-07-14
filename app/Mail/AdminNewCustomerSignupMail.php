<?php

namespace App\Mail;

use App\Models\User;
use App\Support\Emails\AuthEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewCustomerSignupMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody = '';

    public string $plainBody = '';

    public function __construct(
        public User $user,
        public string $phone,
    ) {
        $rendered = app(AuthEmailRenderer::class)->adminNewCustomerSignup($user, $phone);
        $this->htmlBody = $rendered->html;
        $this->plainBody = $rendered->plainBody;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New customer signup',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
            text: $this->plainBody,
        );
    }
}
