<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\User;
use App\Support\Emails\AuthEmailRenderer;
use App\Support\Emails\EmailRecipientRoleSubjectTagger;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewCustomerSignupMail extends Mailable
{
    use Queueable, SerializesModels, RendersModernCustomerEmail;

    public function __construct(
        public User $user,
        public string $phone,
    ) {
        $rendered = app(AuthEmailRenderer::class)->adminNewCustomerSignup($user, $phone);
        $this->applyModernCustomerEmail($rendered);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: EmailRecipientRoleSubjectTagger::apply('New customer signup', 'admin'),
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }
}
