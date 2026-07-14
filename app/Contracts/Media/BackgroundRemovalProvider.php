<?php

namespace App\Contracts\Media;

use App\Data\Media\BackgroundRemovalHealthResult;
use App\Data\Media\BackgroundRemovalInput;
use App\Data\Media\BackgroundRemovalResult;

interface BackgroundRemovalProvider
{
    public function remove(BackgroundRemovalInput $input): BackgroundRemovalResult;

    public function providerName(): string;

    public function isConfigured(): bool;

    public function healthCheck(): BackgroundRemovalHealthResult;
}
