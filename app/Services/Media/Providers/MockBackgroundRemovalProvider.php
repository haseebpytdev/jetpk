<?php

namespace App\Services\Media\Providers;

use App\Contracts\Media\BackgroundRemovalProvider;
use App\Data\Media\BackgroundRemovalHealthResult;
use App\Data\Media\BackgroundRemovalInput;
use App\Data\Media\BackgroundRemovalResult;

/**
 * Test-only provider with configurable responses (no outbound HTTP).
 */
final class MockBackgroundRemovalProvider implements BackgroundRemovalProvider
{
    /** @var callable(BackgroundRemovalInput): BackgroundRemovalResult|null */
    public static $handler = null;

    public static function reset(): void
    {
        self::$handler = null;
    }

    public function remove(BackgroundRemovalInput $input): BackgroundRemovalResult
    {
        if (self::$handler !== null) {
            return (self::$handler)($input);
        }

        return BackgroundRemovalResult::failed('not_configured', 'Mock provider handler not configured.');
    }

    public function providerName(): string
    {
        return 'mock';
    }

    public function isConfigured(): bool
    {
        return self::$handler !== null;
    }

    public function healthCheck(): BackgroundRemovalHealthResult
    {
        return new BackgroundRemovalHealthResult(
            $this->isConfigured(),
            $this->isConfigured() ? 'Mock provider ready.' : 'Mock provider not configured.',
            $this->isConfigured() ? null : 'not_configured',
        );
    }
}
