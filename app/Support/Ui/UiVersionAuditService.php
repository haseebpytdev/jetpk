<?php

namespace App\Support\Ui;

use Illuminate\Support\Facades\View;

/**
 * Read-only UI version configuration and view/asset audit snapshots.
 */
class UiVersionAuditService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $channels = [];
        $failures = 0;
        $warnings = 0;

        foreach ($this->configuredChannels() as $channelKey) {
            $channelConfig = config("ota-ui.channels.{$channelKey}", []);
            $fallback = (string) ($channelConfig['fallback'] ?? 'v1');
            $active = is_array($channelConfig['active_versions'] ?? null)
                ? array_values($channelConfig['active_versions'])
                : ['v1'];

            $critical = config("ota-ui.critical_views.{$channelKey}", []);
            $critical = is_array($critical) ? $critical : [];

            $criticalResults = [];
            foreach ($critical as $logicalPath) {
                if (! is_string($logicalPath) || $logicalPath === '') {
                    continue;
                }

                $exists = View::exists($logicalPath);
                $criticalResults[] = [
                    'path' => $logicalPath,
                    'exists' => $exists,
                    'required' => true,
                ];

                if (! $exists) {
                    $failures++;
                }
            }

            $overlayResults = [];
            foreach ($active as $version) {
                if ($version === $fallback) {
                    continue;
                }

                foreach ($critical as $logicalPath) {
                    if (! is_string($logicalPath) || $logicalPath === '') {
                        continue;
                    }

                    $overlay = $this->overlayViewName($channelKey, $version, $logicalPath);
                    $exists = View::exists($overlay);
                    $overlayResults[] = [
                        'path' => $overlay,
                        'logical_path' => $logicalPath,
                        'version' => $version,
                        'exists' => $exists,
                    ];

                    if (! $exists) {
                        $warnings++;
                    }
                }
            }

            $channels[$channelKey] = [
                'default' => (string) ($channelConfig['default'] ?? 'v1'),
                'active_versions' => $active,
                'fallback' => $fallback,
                'preview_enabled' => (bool) ($channelConfig['preview_enabled'] ?? false),
                'route_prefix_versions' => is_array($channelConfig['route_prefix_versions'] ?? null)
                    ? array_values($channelConfig['route_prefix_versions'])
                    : [],
                'critical_views' => $criticalResults,
                'overlay_views' => $overlayResults,
            ];
        }

        return [
            'channels' => $channels,
            'preview_query_param' => (string) config('ota-ui.preview_query_param', 'ui'),
            'public_asset_root_reminder' => (string) config('ota-ui.public_asset_root_reminder', ''),
            'branded_fare_width' => $this->brandedFareWidthAudit(),
            'counts' => [
                'fail' => $failures + ($this->brandedFareWidthAudit()['fail'] ?? 0),
                'warn' => $warnings,
            ],
        ];
    }

    /**
     * @return array{pass: bool, fail: int, token_present: bool, forbidden_304px: bool, forbidden_300px_branded: bool, details: list<string>}
     */
    public function brandedFareWidthAudit(): array
    {
        $details = [];
        $fail = 0;
        $cssPath = base_path('public/css/ota-public.css');
        $css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

        $tokenPresent = str_contains($css, '--ota-branded-fare-card-width: 264px');
        if (! $tokenPresent) {
            $fail++;
            $details[] = 'missing_global_264px_token';
        }

        $forbidden304 = (bool) preg_match('/ota-branded-fare[^\n{]*\{[^}]*304px|branded-fare-card[^\n{]*\{[^}]*304px/i', $css);
        if ($forbidden304) {
            $fail++;
            $details[] = 'forbidden_304px_branded_fare_rule';
        }

        $forbidden300Branded = (bool) preg_match('/--ota-branded-fare-card-width:\s*300px/i', $css);
        if ($forbidden300Branded) {
            $fail++;
            $details[] = 'forbidden_300px_branded_fare_token';
        }

        return [
            'pass' => $fail === 0,
            'fail' => $fail,
            'token_present' => $tokenPresent,
            'forbidden_304px' => $forbidden304,
            'forbidden_300px_branded' => $forbidden300Branded,
            'details' => $details,
        ];
    }

    /**
     * @return list<string>
     */
    public function configuredChannels(): array
    {
        $channels = config('ota-ui.channels', []);

        return is_array($channels) ? array_keys($channels) : [];
    }

    protected function overlayViewName(string $channel, string $version, string $logicalPath): string
    {
        $segments = explode('.', $logicalPath);

        return 'ui/'.$channel.'/'.$version.'/'.implode('/', $segments);
    }
}
