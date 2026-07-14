<?php

namespace App\Console\Commands;

use App\Services\Branding\ClientThemePaletteService;
use App\Services\Client\ClientProfileResolver;
use Illuminate\Console\Command;

class OtaClientLogoPaletteCommand extends Command
{
    protected $signature = 'ota:client-logo-palette
                            {--client=jetpk : Client slug}
                            {--logo= : Relative path under public/ (optional)}';

    protected $description = 'Generate draft theme palette from client logo (read-only DB write for palette row)';

    public function handle(ClientProfileResolver $profileResolver, ClientThemePaletteService $paletteService): int
    {
        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile === null) {
            $this->error("Client not found: {$slug}");

            return self::FAILURE;
        }

        $logo = trim((string) $this->option('logo'));
        $palette = $paletteService->generateForProfile($profile, $logo !== '' ? $logo : null);

        $this->table(['token', 'value'], [
            ['primary', $palette->primary],
            ['secondary', $palette->secondary],
            ['accent', $palette->accent],
            ['background', $palette->background],
            ['surface', $palette->surface],
            ['text', $palette->text],
            ['muted', $palette->muted],
        ]);

        $warnings = $palette->palette_json['contrast_warnings'] ?? [];
        foreach ($warnings as $warning) {
            $this->warn((string) $warning);
        }

        $this->info('Draft palette stored. Approve via admin Page Settings → Theme palette.');

        return self::SUCCESS;
    }
}
