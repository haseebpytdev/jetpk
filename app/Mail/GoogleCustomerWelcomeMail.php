<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\User;
use App\Support\Auth\LoginDestination;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\CustomerFacingEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleCustomerWelcomeMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    public function __construct(
        public User $user,
        public string $brandName,
        public string $supportEmail,
        public string $dashboardUrl,
    ) {
        $this->applyModernCustomerEmail(
            app(CustomerFacingEmailRenderer::class)->googleCustomerWelcome($user, $dashboardUrl)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.$this->brandName.' — your account is ready',
        );
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }

    public static function forUser(User $user): self
    {
        $user->loadMissing('currentAgency.agencySetting');
        $profile = CompanyEmailProfileResolver::resolve($user->currentAgency);

        return new self(
            user: $user,
            brandName: $profile->name,
            supportEmail: (string) ($profile->support_email ?: config('ota-brand.support_email', 'support@example.com')),
            dashboardUrl: url(LoginDestination::path($user)),
        );
    }
}
