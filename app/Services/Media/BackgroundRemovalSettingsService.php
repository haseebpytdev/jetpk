<?php

namespace App\Services\Media;

use App\Models\Agency;
use App\Models\BackgroundRemovalSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Media\Providers\FixtureBackgroundRemovalProvider;
use App\Services\Media\Providers\MockBackgroundRemovalProvider;
use App\Services\Media\Providers\NullBackgroundRemovalProvider;
use App\Services\Media\Providers\RemoveBgBackgroundRemovalProvider;
use App\Contracts\Media\BackgroundRemovalProvider;
use App\Support\Media\BackgroundRemovalEndpointValidator;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Schema;

final class BackgroundRemovalSettingsService
{
    public function __construct(
        private readonly BackgroundRemovalEndpointValidator $endpointValidator,
    ) {}

    public function getForAgency(Agency $agency): BackgroundRemovalSetting
    {
        if (! Schema::hasTable('background_removal_settings')) {
            return new BackgroundRemovalSetting([
                'agency_id' => $agency->id,
                'provider' => 'disabled',
                'timeout_seconds' => 30,
                'max_source_bytes' => 5_242_880,
                'max_source_pixels' => 16_777_216,
                'is_enabled' => false,
                'default_for_logos' => false,
            ]);
        }

        return BackgroundRemovalSetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            [
                'provider' => 'disabled',
                'api_endpoint' => config('background-removal.remove_bg.endpoint'),
                'timeout_seconds' => 30,
                'max_source_bytes' => 5_242_880,
                'max_source_pixels' => 16_777_216,
                'is_enabled' => false,
                'default_for_logos' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Agency $agency, User $actor, array $payload): BackgroundRemovalSetting
    {
        $setting = $this->getForAgency($agency);

        if (array_key_exists('api_key', $payload)) {
            $key = trim((string) $payload['api_key']);
            if ($key === '' || $key === '********') {
                unset($payload['api_key']);
            }
        }

        if (array_key_exists('api_endpoint', $payload)) {
            $this->endpointValidator->assertSafeHttpsEndpoint(
                is_string($payload['api_endpoint']) ? $payload['api_endpoint'] : null,
            );
            $this->assertAllowedRemoveBgHost(
                is_string($payload['api_endpoint']) ? $payload['api_endpoint'] : null,
            );
        }

        if (array_key_exists('timeout_seconds', $payload)) {
            $payload['timeout_seconds'] = min(
                (int) $payload['timeout_seconds'],
                (int) config('background-removal.max_timeout_seconds', 120),
            );
        }

        $setting->fill($payload);
        $setting->save();

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'background_removal.settings_updated',
            'auditable_type' => BackgroundRemovalSetting::class,
            'auditable_id' => $setting->id,
            'properties' => [
                'old_values' => [],
                'new_values' => SensitiveDataRedactor::redact([
                    'provider' => $setting->provider,
                    'is_enabled' => $setting->is_enabled,
                    'default_for_logos' => $setting->default_for_logos,
                    'timeout_seconds' => $setting->timeout_seconds,
                    'api_endpoint' => $setting->api_endpoint,
                ]),
            ],
        ]);

        return $setting->fresh();
    }

    public function resolveProvider(?BackgroundRemovalSetting $setting = null): BackgroundRemovalProvider
    {
        if (config('background-removal.force_fixture_provider')) {
            return new FixtureBackgroundRemovalProvider;
        }

        if ($setting === null || ! $setting->is_enabled || $setting->provider === 'disabled') {
            if (config('background-removal.force_mock_provider')) {
                return new MockBackgroundRemovalProvider;
            }

            return new NullBackgroundRemovalProvider;
        }

        return match ($setting->provider) {
            'mock' => new MockBackgroundRemovalProvider,
            'test_fixture' => new FixtureBackgroundRemovalProvider,
            'remove_bg' => new RemoveBgBackgroundRemovalProvider(
                $setting->api_key,
                $setting->api_endpoint ?: (string) config('background-removal.remove_bg.endpoint'),
            ),
            default => new NullBackgroundRemovalProvider,
        };
    }

    private function assertAllowedRemoveBgHost(?string $endpoint): void
    {
        if ($endpoint === null || trim($endpoint) === '') {
            return;
        }

        $host = strtolower((string) parse_url(trim($endpoint), PHP_URL_HOST));
        $allowed = config('background-removal.remove_bg.allowed_hosts', []);
        if ($allowed === [] || in_array($host, $allowed, true)) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'api_endpoint' => 'Background-removal endpoint host is not on the allowlist.',
        ]);
    }
}
