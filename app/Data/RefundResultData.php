<?php

namespace App\Data;

class RefundResultData
{
    /**
     * @param  array<string, mixed>  $safe_summary
     */
    public function __construct(
        public bool $success,
        public string $status,
        public string $provider,
        public string $outcome = 'UNKNOWN',
        public array $safe_summary = [],
        public ?string $error_code = null,
        public ?string $error_message = null,
        public int $provider_calls = 0,
    ) {}
}
