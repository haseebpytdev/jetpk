<?php

namespace App\Console\Commands;

use App\Support\Ui\UiVersionAuditService;
use Illuminate\Console\Command;

class OtaUiVersionAuditCommand extends Command
{
    protected $signature = 'ota:ui-version-audit';

    protected $description = 'Read-only audit of UI version channels, defaults, active versions, and critical view fallbacks.';

    public function handle(UiVersionAuditService $audit): int
    {
        $snapshot = $audit->snapshot();

        $this->line('OTA UI Version Audit');
        $this->line('Channels: '.implode(', ', $audit->configuredChannels()));
        $this->newLine();

        foreach ($snapshot['channels'] as $channelKey => $channel) {
            $active = implode(',', $channel['active_versions'] ?? []);
            $preview = ($channel['preview_enabled'] ?? false) ? 'enabled' : 'disabled';
            $prefixes = $channel['route_prefix_versions'] ?? [];
            $prefixNote = $prefixes !== []
                ? ' route_prefix=['.implode(',', $prefixes).']'
                : ' query_param='.($snapshot['preview_query_param'] ?? 'ui');

            $this->line(sprintf(
                '%s: default=%s active=[%s] preview=%s fallback=%s%s',
                $channelKey,
                $channel['default'] ?? 'v1',
                $active,
                $preview,
                $channel['fallback'] ?? 'v1',
                $prefixNote,
            ));

            foreach ($channel['critical_views'] ?? [] as $view) {
                $status = ($view['exists'] ?? false) ? 'present' : 'MISSING';
                $this->line(sprintf('  critical %s: %s', $view['path'] ?? '', $status));
            }
        }

        $overlayMissing = 0;
        foreach ($snapshot['channels'] as $channel) {
            foreach ($channel['overlay_views'] ?? [] as $overlay) {
                if (! ($overlay['exists'] ?? false)) {
                    $overlayMissing++;
                }
            }
        }

        $this->newLine();
        if ($overlayMissing > 0) {
            $this->line("v2 overlays: {$overlayMissing} missing (fallback OK)");
        } else {
            $this->line('v2 overlays: none or all present (fallback OK)');
        }

        $assetRoot = trim((string) ($snapshot['public_asset_root_reminder'] ?? ''));
        if ($assetRoot !== '') {
            $this->line('Public assets: upload versioned CSS/JS to '.$assetRoot.'/css and '.$assetRoot.'/js when added');
        }

        $failCount = (int) ($snapshot['counts']['fail'] ?? 0);
        $this->newLine();
        $this->line('fail='.$failCount);

        if ($failCount > 0) {
            $this->error('UI version audit completed with failures.');

            return self::FAILURE;
        }

        $this->info('UI version audit passed.');

        return self::SUCCESS;
    }
}
