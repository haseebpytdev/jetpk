<?php

namespace App\Services\Visa\DTO;

final readonly class VisaCaptchaChallenge
{
    /**
     * @param  string  $imageBase64  Raw image bytes as base64 (no data-URI prefix required)
     */
    public function __construct(
        public string $lookupSessionId,
        public string $mimeType,
        public string $imageBase64,
        public int $expiresAt,
    ) {}
}
