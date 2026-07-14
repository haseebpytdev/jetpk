<?php

namespace App\Data\Media;

final class BackgroundRemovalHealthResult
{
    public function __construct(
        public readonly bool $healthy,
        public readonly string $message,
        public readonly ?string $errorCode = null,
    ) {}
}
