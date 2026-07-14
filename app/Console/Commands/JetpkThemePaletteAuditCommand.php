<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Branding\JetpkThemePaletteService;
use App\Support\Branding\JetpkThemePaletteValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class JetpkThemePaletteAuditCommand extends Command
{
    protected $signature = 'jetpk:theme-palette-audit';

    protected $description = 'Audit JetPakistan day/night theme palette tokens, contrast, and orange primary usage';

    /** @var list<string> */
    private const TOKEN_COVERAGE_FILES = [
        'css/booking.css',
        'css/flight-cards.css',
        'css/jp-search.css',
        'css/results-base.css',
        'css/results.css',
    ];

    public function handle(
        JetpkThemePaletteService $paletteService,
        JetpkThemePaletteValidator $validator,
    ): int {
        $fail = 0;
        $coverageWarnings = 0;
        $orangeHits = 0;

        if (! $paletteService->isJetpkScoped()) {
            $this->line('scoped=0 fail_count=1');

            return self::FAILURE;
        }

        $agency = Agency::query()->orderBy('id')->first();
        if ($agency === null) {
            $this->line('fail_count=1');

            return self::FAILURE;
        }

        $palettes = $paletteService->palettesForAgency($agency);
        $defaults = $paletteService->defaults();
        $approvedDayPrimary = strtoupper((string) ($defaults['day']['primary'] ?? '#63B32E'));
        $approvedNightPrimary = strtoupper((string) ($defaults['night']['primary'] ?? '#63B32E'));

        $dayPrimary = strtoupper((string) ($palettes['day']['primary'] ?? ''));
        $nightPrimary = strtoupper((string) ($palettes['night']['primary'] ?? ''));

        $this->line('DAY primary='.$dayPrimary);
        $this->line('NIGHT primary='.$nightPrimary);

        if ($paletteService->isLegacyDayPrimary($dayPrimary)) {
            $this->error('  day primary is legacy/system value; run jetpk:theme-palette-normalize-day-default');
            $fail++;
        }

        foreach (['day', 'night'] as $theme) {
            foreach ($palettes[$theme] as $key => $value) {
                if ($validator->normalizeHex($value) === null) {
                    $this->error("  invalid hex {$theme}.{$key}");
                    $fail++;
                }
            }

            foreach ($validator->validatePalette($theme, $palettes[$theme]) as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error("  contrast/validation {$theme}.{$field}: {$message}");
                    $fail++;
                }
            }
        }

        $frontendRoot = public_path('themes/frontend/jetpakistan');
        $orangePattern = '/#(?:EA7A1E|FB923C|F97316|FF7A00|FF8A00|F59433)/i';

        foreach (File::allFiles($frontendRoot) as $file) {
            if ($file->getExtension() !== 'css') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match_all($orangePattern, $contents, $matches)) {
                $relative = str_replace(public_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                $this->error("  raw orange in {$relative}: ".implode(', ', array_unique($matches[0])));
                $orangeHits += count(array_unique($matches[0]));
                $fail++;
            }
        }

        foreach (self::TOKEN_COVERAGE_FILES as $relative) {
            $path = $frontendRoot.'/'.$relative;
            if (! is_file($path)) {
                $this->error("  missing css file {$relative}");
                $fail++;

                continue;
            }
            $contents = (string) file_get_contents($path);
            if (str_contains($contents, 'btn-primary') && ! str_contains($contents, '--jp-primary')) {
                $this->warn("  token coverage: {$relative} uses btn-primary without --jp-primary");
                $coverageWarnings++;
            }
        }

        $tokensPath = $frontendRoot.'/css/tokens.css';
        if (is_file($tokensPath)) {
            $tokens = (string) file_get_contents($tokensPath);
            foreach (['--jp-primary', '--jp-accent', '[data-theme="day"]'] as $needle) {
                if (! str_contains($tokens, $needle)) {
                    $this->error("  missing token definition: {$needle}");
                    $fail++;
                }
            }
        }

        if ($coverageWarnings > 0) {
            $fail += $coverageWarnings;
        }

        $this->line('remaining btn-primary token coverage warnings='.$coverageWarnings);
        $this->line('raw orange primary hits='.$orangeHits);
        $this->line('fail_count='.$fail);

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
