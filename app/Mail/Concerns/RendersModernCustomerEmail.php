<?php

namespace App\Mail\Concerns;

use App\Support\Emails\CustomerFacingEmailRendered;
use Illuminate\Mail\Mailables\Content;

trait RendersModernCustomerEmail
{
    public string $htmlBody = '';

    public string $plainBody = '';

    protected function applyModernCustomerEmail(CustomerFacingEmailRendered $rendered): void
    {
        $this->htmlBody = $rendered->html;
        $this->plainBody = $rendered->plainBody;

        if ($this->plainBody !== '') {
            $plain = $this->plainBody;
            $this->withSymfonyMessage(static function (\Symfony\Component\Mime\Email $message) use ($plain): void {
                $message->text($plain);
            });
        }
    }

    protected function modernCustomerContent(): Content
    {
        // Content::$text is a Blade view name — never pass raw plain-text body there.
        return new Content(htmlString: $this->htmlBody);
    }
}
