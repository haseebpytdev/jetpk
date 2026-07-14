<?php

namespace App\Data\Media;

final class TransparencyInspection
{
    public function __construct(
        public readonly bool $known,
        public readonly bool $hasAlphaChannel,
        public readonly bool $hasTransparentPixels,
        public readonly float $transparentPixelRatio,
        public readonly float $opaquePixelRatio,
        public readonly bool $isFullyTransparent,
        public readonly bool $isFullyOpaque,
        public readonly ?string $warning = null,
    ) {}

    public static function unknown(?string $warning = null): self
    {
        return new self(
            known: false,
            hasAlphaChannel: false,
            hasTransparentPixels: false,
            transparentPixelRatio: 0.0,
            opaquePixelRatio: 1.0,
            isFullyTransparent: false,
            isFullyOpaque: false,
            warning: $warning,
        );
    }
}
