<?php

namespace App\Services\Media\Providers;

use App\Contracts\Media\BackgroundRemovalProvider;
use App\Data\Media\BackgroundRemovalHealthResult;
use App\Data\Media\BackgroundRemovalInput;
use App\Data\Media\BackgroundRemovalResult;

/**
 * Safe no-op provider when background removal is disabled.
 */
final class NullBackgroundRemovalProvider implements BackgroundRemovalProvider
{
    public function remove(BackgroundRemovalInput $input): BackgroundRemovalResult
    {
        return BackgroundRemovalResult::failed('provider_disabled', 'Background removal is disabled.');
    }

    public function providerName(): string
    {
        return 'disabled';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function healthCheck(): BackgroundRemovalHealthResult
    {
        return new BackgroundRemovalHealthResult(false, 'Background removal is disabled.', 'disabled');
    }
}
