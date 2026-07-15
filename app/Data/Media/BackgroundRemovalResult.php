<?php

namespace App\Data\Media;

final class BackgroundRemovalResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $outputAbsolutePath = null,
        public readonly ?string $providerRequestId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessageSafe = null,
        public readonly int $processingMs = 0,
        public readonly array $warnings = [],
    ) {}

    public static function failed(string $code, string $messageSafe): self
    {
        return new self(false, errorCode: $code, errorMessageSafe: $messageSafe);
    }
}
