<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Branding\JetpkThemePaletteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class JetpkThemePalettePreviewCommand extends Command
{
    protected $signature = 'jetpk:theme-palette-preview {--theme=day : day or night}';

    protected $description = 'Render a safe JetPakistan theme palette preview without modifying settings';

    public function handle(JetpkThemePaletteService $paletteService): int
    {
        $theme = strtolower((string) $this->option('theme'));
        if (! in_array($theme, ['day', 'night'], true)) {
            $this->error('Invalid --theme. Use day or night.');

            return self::FAILURE;
        }

        if (! $paletteService->isJetpkScoped()) {
            $this->error('Not a JetPakistan deployment.');

            return self::FAILURE;
        }

        $agency = Agency::query()->orderBy('id')->first();
        if ($agency === null) {
            $this->error('No agency found.');

            return self::FAILURE;
        }

        $palettes = $paletteService->palettesForAgency($agency);
        $vars = $paletteService->cssVariablesForTheme($theme, $palettes[$theme]);

        $dir = storage_path('app/previews/jetpk-theme-palette');
        File::ensureDirectoryExists($dir);

        $css = ":root {\n";
        foreach ($vars as $name => $value) {
            $css .= "  {$name}: {$value};\n";
        }
        $css .= "}\n";

        $path = $dir.'/'.$theme.'-palette-preview.css';
        File::put($path, $css);

        $this->line('theme='.$theme);
        $this->line('path='.$path);
        $this->line('token_count='.count($vars));

        return self::SUCCESS;
    }
}
