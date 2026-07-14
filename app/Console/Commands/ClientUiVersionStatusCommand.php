<?php

namespace App\Console\Commands;

use App\Support\Ui\ClientUiVersionStatusService;
use Illuminate\Console\Command;

class ClientUiVersionStatusCommand extends Command
{
    protected $signature = 'ota:client-ui-version-status';

    protected $description = 'Read-only status for V2-MC-0 protected client UI preview lane (v1 default, /v2 namespace).';

    public function handle(ClientUiVersionStatusService $status): int
    {
        $snapshot = $status->snapshot();

        $this->info('OTA Client UI Version Status (V2-MC-0)');
        $this->newLine();

        $this->line('UI versioning enabled: '.($snapshot['versioning_enabled'] ? 'yes' : 'no'));
        $this->line('default version: '.$snapshot['default_version']);
        $this->line('force v1 default enabled: '.($snapshot['force_v1_default'] ? 'yes' : 'no'));
        $this->line('preview enabled: '.($snapshot['preview_enabled'] ? 'yes' : 'no'));
        $this->line('allowed versions: '.implode(', ', $snapshot['allowed_versions'] ?? []));
        $this->line('v2 namespace enabled: '.($snapshot['namespace_enabled'] ? 'yes' : 'no'));
        $this->line('namespace: /'.$snapshot['namespace']);
        $this->line('preview protection enabled: '.($snapshot['protection_enabled'] ? 'yes' : 'no'));
        $this->line('preview key configured: '.($snapshot['preview_key_configured'] ? 'yes' : 'no'));
        $this->line('session sticky preview enabled: '.($snapshot['session_sticky_enabled'] ? 'yes' : 'no'));
        $this->line('reserved slug includes v2: '.($snapshot['reserved_v2'] ? 'yes' : 'no'));
        $this->line('reserved slug includes ui: '.($snapshot['reserved_ui'] ? 'yes' : 'no'));
        $this->line('GET /v2 route exists: '.($snapshot['middleware_namespace_dispatch'] ? 'yes (middleware dispatch)' : 'no'));
        $this->line('GET /v2/{any} route exists: '.($snapshot['middleware_namespace_dispatch'] ? 'yes (middleware dispatch)' : 'no'));
        $this->line('POST/PUT/PATCH/DELETE /v2 catch-all blocked: '.(($snapshot['mutation_v2_routes'] ?? 0) === 0 ? 'yes' : 'no'));

        $this->newLine();
        $this->line('v1 asset files discovered:');
        foreach (array_merge($snapshot['v1_css'] ?? [], $snapshot['v1_js'] ?? []) as $asset) {
            $this->line('  '.$asset);
        }

        $this->newLine();
        $this->line('v2 cloned asset files found:');
        foreach ($snapshot['v2_clones'] ?? [] as $clone) {
            $exists = ! in_array($clone, $snapshot['missing_v2_clones'] ?? [], true);
            $this->line('  '.$clone.($exists ? '' : ' MISSING'));
        }

        if (($snapshot['missing_v2_clones'] ?? []) !== []) {
            $this->newLine();
            $this->error('Missing v2 clones:');
            foreach ($snapshot['missing_v2_clones'] as $missing) {
                $this->line('  '.$missing);
            }
        }

        $this->newLine();
        $this->line('layouts updated count: '.($snapshot['layouts_updated'] ?? 0));
        $this->line('layout nav v2-preserve helpers present: '.($snapshot['helpers_present'] ? 'yes' : 'no'));

        $this->newLine();
        $this->line('preview routes found:');
        foreach ($snapshot['preview_routes'] ?? [] as $routeLine) {
            $this->line('  '.$routeLine);
        }

        foreach ($snapshot['warnings'] ?? [] as $warning) {
            $this->warn('Warning: '.$warning);
        }

        $fail = count($snapshot['missing_v2_clones'] ?? []) > 0 ? 1 : 0;
        $this->newLine();
        $this->line('fail='.$fail);

        if ($fail > 0) {
            return self::FAILURE;
        }

        $this->info('Client UI version status passed.');

        return self::SUCCESS;
    }
}
