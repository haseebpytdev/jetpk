<?php

namespace App\Data;

/**
 * Safe outcome for Sabre booking pipeline steps (no live GDS payloads).
 *
 * @param  array<string, mixed>  $safe_context
 */
final class SabreBookingOperationResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public string $message,
        public array $safe_context = [],
    ) {}
}
