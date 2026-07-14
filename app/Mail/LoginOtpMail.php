<?php

namespace App\Mail;

use App\Mail\Concerns\RendersModernCustomerEmail;
use App\Models\User;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Branding\ClientMailBrandingResolver;
use App\Support\Emails\AuthEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, RendersModernCustomerEmail, SerializesModels;

    private readonly ?\App\Support\Branding\ClientMailBrandingProfile $clientBranding;

    public function __construct(
        public User $user,
        public string $brandName,
        public string $otpCode,
        public int $expiryMinutes,
        public ?string $clientSlug = null,
    ) {
        $this->clientBranding = $this->usesClientBranding()
            ? ClientMailBrandingResolver::resolve($this->resolvedClientSlug())
            : null;

        $this->applyModernCustomerEmail(
            app(AuthEmailRenderer::class)->loginOtp(
                user: $user,
                brandName: $this->displayBrandName(),
                otpCode: $otpCode,
                expiryMinutes: $expiryMinutes,
            )
        );
    }

    public function envelope(): Envelope
    {
        $displayName = $this->displayBrandName();
        $fromAddress = trim((string) config('mail.from.address', ''));

        $envelope = new Envelope(
            subject: $this->clientBranding !== null
                ? $this->clientBranding->loginOtpSubject()
                : 'Your '.$displayName.' login OTP',
        );

        if ($fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false) {
            $fromName = $this->clientBranding?->mailFromName ?? $displayName;
            $fromName = trim($fromName) !== '' ? trim($fromName) : (string) config('mail.from.name', $displayName);
            $envelope = $envelope->from(new Address($fromAddress, $fromName));
        }

        $replyTo = $this->clientBranding?->replyToEmail;
        if (is_string($replyTo) && $replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $envelope = $envelope->replyTo([new Address($replyTo, $displayName)]);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return $this->modernCustomerContent();
    }

    private function usesClientBranding(): bool
    {
        return $this->resolvedClientSlug() === 'jetpk' || is_client_preview();
    }

    private function resolvedClientSlug(): ?string
    {
        if (is_string($this->clientSlug) && trim($this->clientSlug) !== '') {
            return trim($this->clientSlug);
        }

        return ClientLoginOtpGate::resolvedClientSlug();
    }

    private function displayBrandName(): string
    {
        if ($this->clientBranding !== null) {
            return $this->clientBranding->companyName;
        }

        return $this->brandName !== '' ? $this->brandName : (string) config('app.name', 'OTA');
    }
}
