<?php

namespace App\Services\Media\Providers;

use App\Contracts\Media\BackgroundRemovalProvider;
use App\Data\Media\BackgroundRemovalHealthResult;
use App\Data\Media\BackgroundRemovalInput;
use App\Data\Media\BackgroundRemovalResult;

/**
 * Local/Playwright provider that copies a transparent PNG fixture (no paid API).
 */
final class FixtureBackgroundRemovalProvider implements BackgroundRemovalProvider
{
    public function remove(BackgroundRemovalInput $input): BackgroundRemovalResult
    {
        $fixture = $this->fixturePath();
        if (! is_file($fixture)) {
            return BackgroundRemovalResult::failed('fixture_missing', 'Transparent logo fixture is missing.');
        }

        $started = hrtime(true);
        $temp = tempnam(sys_get_temp_dir(), 'jp-bg-fixture-');
        if ($temp === false) {
            return BackgroundRemovalResult::failed('storage_error', 'Could not prepare processed image storage.');
        }

        $pngPath = $temp.'.png';
        @unlink($temp);
        if (! @copy($fixture, $pngPath)) {
            return BackgroundRemovalResult::failed('storage_error', 'Could not copy fixture image.');
        }

        $processingMs = (int) round((hrtime(true) - $started) / 1_000_000);

        return new BackgroundRemovalResult(
            success: true,
            outputAbsolutePath: $pngPath,
            providerRequestId: 'fixture-local',
            processingMs: $processingMs,
        );
    }

    public function providerName(): string
    {
        return 'test_fixture';
    }

    public function isConfigured(): bool
    {
        return is_file($this->fixturePath());
    }

    public function healthCheck(): BackgroundRemovalHealthResult
    {
        return new BackgroundRemovalHealthResult(
            $this->isConfigured(),
            $this->isConfigured() ? 'Fixture provider ready.' : 'Fixture PNG missing.',
            $this->isConfigured() ? null : 'fixture_missing',
        );
    }

    private function fixturePath(): string
    {
        $configured = (string) config('background-removal.fixture_path');

        return $configured !== ''
            ? $configured
            : base_path('tests/fixtures/branding/transparent-logo.png');
    }
}
