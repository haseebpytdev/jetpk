<?php

namespace App\Services\Media\Providers;

use App\Contracts\Media\BackgroundRemovalProvider;
use App\Data\Media\BackgroundRemovalHealthResult;
use App\Data\Media\BackgroundRemovalInput;
use App\Data\Media\BackgroundRemovalResult;
use Illuminate\Support\Facades\Http;

/**
 * remove.bg-compatible HTTP API adapter.
 */
final class RemoveBgBackgroundRemovalProvider implements BackgroundRemovalProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $endpoint,
    ) {}

    public function remove(BackgroundRemovalInput $input): BackgroundRemovalResult
    {
        if (! $this->isConfigured()) {
            return BackgroundRemovalResult::failed('provider_disabled', 'Background removal is not configured.');
        }

        $started = hrtime(true);

        try {
            $response = Http::timeout($input->timeoutSeconds)
                ->withHeaders(['X-Api-Key' => (string) $this->apiKey])
                ->attach('image_file', fopen($input->absoluteSourcePath, 'rb'), basename($input->absoluteSourcePath))
                ->post($this->endpoint, [
                    'size' => 'auto',
                    'format' => 'png',
                ]);

            $processingMs = (int) round((hrtime(true) - $started) / 1_000_000);

            if ($response->status() === 429) {
                return BackgroundRemovalResult::failed('rate_limited', 'The background-removal provider is rate limited. Try again shortly.');
            }

            if ($response->status() === 402) {
                return BackgroundRemovalResult::failed('quota_exceeded', 'The background-removal provider quota has been exceeded.');
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return BackgroundRemovalResult::failed('invalid_api_key', 'The background-removal API key is invalid.');
            }

            if (! $response->successful()) {
                return BackgroundRemovalResult::failed(
                    'provider_error',
                    'Background removal failed. Please try again or keep the original logo.',
                );
            }

            $temp = tempnam(sys_get_temp_dir(), 'jp-bg-');
            if ($temp === false) {
                return BackgroundRemovalResult::failed('storage_error', 'Could not prepare processed image storage.');
            }

            $pngPath = $temp.'.png';
            @unlink($temp);
            file_put_contents($pngPath, $response->body());

            return new BackgroundRemovalResult(
                success: true,
                outputAbsolutePath: $pngPath,
                providerRequestId: $response->header('X-Request-Id') ?: $response->header('x-request-id'),
                processingMs: $processingMs,
            );
        } catch (\Throwable $e) {
            report($e);

            return BackgroundRemovalResult::failed(
                'provider_timeout',
                'Background removal timed out or failed. Please try again or keep the original logo.',
            );
        }
    }

    public function providerName(): string
    {
        return 'remove_bg';
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->endpoint);
    }

    public function healthCheck(): BackgroundRemovalHealthResult
    {
        if (! $this->isConfigured()) {
            return new BackgroundRemovalHealthResult(false, 'API key or endpoint is missing.', 'not_configured');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Api-Key' => (string) $this->apiKey])
                ->get(rtrim($this->endpoint, '/').'/account');

            if ($response->status() === 401 || $response->status() === 403) {
                return new BackgroundRemovalHealthResult(false, 'API key rejected by provider.', 'invalid_api_key');
            }

            if ($response->successful()) {
                return new BackgroundRemovalHealthResult(true, 'Provider connection OK.');
            }

            return new BackgroundRemovalHealthResult(false, 'Provider health check failed.', 'provider_error');
        } catch (\Throwable $e) {
            report($e);

            return new BackgroundRemovalHealthResult(false, 'Could not reach background-removal provider.', 'network_error');
        }
    }
}
