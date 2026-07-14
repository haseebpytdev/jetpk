<?php

namespace App\Services\Suppliers\PiaNdc\Exceptions;

use RuntimeException;

class PiaNdcException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $normalizedCode,
        public readonly int $httpStatus,
        public readonly string $safeMessage,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, $httpStatus, $previous);
    }

    /**
     * Safe diagnostic fields for logs, CLI output, and search meta.
     *
     * @return array<string, mixed>
     */
    public function safeDiagnosticMeta(?string $operation = null): array
    {
        $meta = ['error_code' => $this->normalizedCode];

        foreach (['correlation_id', 'provider_errors', 'http_status', 'fault_code', 'fault_message', 'operation', 'endpoint'] as $key) {
            if (array_key_exists($key, $this->context)) {
                $meta[$key] = $this->context[$key];
            }
        }

        if ($operation !== null && ! array_key_exists('operation', $meta)) {
            $meta['operation'] = $operation;
        }

        if (! array_key_exists('http_status', $meta)) {
            $meta['http_status'] = $this->httpStatus;
        }

        return $meta;
    }
}
