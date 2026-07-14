<?php

namespace App\Data\Email;

/**
 * Structured payload for JetPK operational email delivery.
 */
readonly class OperationalEmailData
{
    /**
     * @param  array<string, scalar|null>  $templateVariables
     * @param  array<string, mixed>  $payload
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     */
    public function __construct(
        public string $eventKey,
        public string $recipientEmail,
        public string $recipientRole,
        public ?string $recipientDesignation = null,
        public ?string $deliveryVariant = null,
        public ?string $recipientBucket = null,
        public array $templateVariables = [],
        public array $payload = [],
        public ?string $fallbackSubject = null,
        public string $fallbackBody = '',
        public array $attachments = [],
        public ?string $dedupReference = null,
    ) {}

    public function maskedRecipientEmail(): string
    {
        $email = strtolower(trim($this->recipientEmail));
        if ($email === '' || ! str_contains($email, '@')) {
            return '[invalid]';
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }
}
