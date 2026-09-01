<?php

namespace App\Services\Visa\DTO;

final readonly class VisaLookupRequest
{
    public function __construct(
        public string $firstCriterion,
        public string $firstValue,
        public string $secondCriterion,
        public string $secondValue,
        public string $nationality,
        public string $captchaAnswer,
        public string $lookupSessionId,
    ) {}
}
