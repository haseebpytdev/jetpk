<?php

namespace App\Data;

class TicketingResultData
{
    /**
     * @param  list<array<string, mixed>>  $tickets
     * @param  array<string, mixed>  $safe_summary
     * @param  array<string, mixed>|null  $request_payload
     * @param  array<string, mixed>|null  $response_payload
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $success,
        public string $status,
        public string $provider,
        public array $tickets = [],
        public array $safe_summary = [],
        public ?array $request_payload = null,
        public ?array $response_payload = null,
        public ?string $error_code = null,
        public ?string $error_message = null,
        public array $warnings = [],
    ) {}
}
