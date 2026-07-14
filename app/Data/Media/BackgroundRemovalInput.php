<?php

namespace App\Data\Media;

final class BackgroundRemovalInput
{
    public function __construct(
        public readonly string $absoluteSourcePath,
        public readonly string $sourceMime,
        public readonly int $timeoutSeconds,
        public readonly ?string $idempotencyKey = null,
    ) {}
}
