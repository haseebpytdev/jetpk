<?php

namespace App\Services\Visa\DTO;

final readonly class VisaExport
{
    public function __construct(
        public string $format,
        public string $mimeType,
        public string $bytes,
        public string $sha256,
        public string $label,
        public string $attribution,
    ) {}
}
