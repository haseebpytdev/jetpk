<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\User;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Emails\AuthEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWelcomeMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    public function __construct(
        public User $user,
        public string $brandName,
    ) {
        $this->applyModernCustomerEmail(
            app(AuthEmailRenderer::class)->customerWelcome($user, $brandName)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.$this->brandName.' — please verify your email',
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }

    public static function forUser(User $user): self
    {
        $user->loadMissing('currentAgency.agencySetting');

        return new self(
            user: $user,
            brandName: BrandDisplayResolver::displayName(),
        );
    }
}
