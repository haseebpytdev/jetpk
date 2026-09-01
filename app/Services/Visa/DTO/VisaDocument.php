<?php

namespace App\Services\Visa\DTO;

final readonly class VisaDocument
{
    public function __construct(
        public string $ref,
        public string $sourceType,
        public string $mimeType,
        public string $bytes,
        public string $sha256,
        public string $attribution,
    ) {}
}
