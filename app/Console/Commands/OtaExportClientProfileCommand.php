<?php

namespace App\Console\Commands;

use App\Support\Client\ClientProfileExporter;
use Illuminate\Console\Command;
use RuntimeException;

class OtaExportClientProfileCommand extends Command
{
    protected $signature = 'ota:export-client-profile
                            {slug? : Client slug; defaults to config(ota_client.slug)}
                            {--from-db : Merge branding from default agency DB settings when available}
                            {--include-assets : Copy logo/favicon/banners into public/client-assets/{profile}}
                            {--force : Overwrite an existing clients/{slug} export}';

    protected $description = 'Export the current live client deployment profile into clients/{slug}/ (no secrets)';

    public function handle(ClientProfileExporter $exporter): int
    {
        try {
            $result = $exporter->export(
                slug: $this->argument('slug'),
                fromDb: (bool) $this->option('from-db'),
                includeAssets: (bool) $this->option('include-assets'),
                force: (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Client profile exported successfully.');
        $this->line('Slug: '.$result['slug']);
        $this->line('Client folder: '.$result['client_dir']);
        $this->line('Assets folder: '.$result['assets_dir']);
        $this->newLine();
        $this->line('Files written:');
        foreach ($result['files'] as $file) {
            $this->line(' - '.$file);
        }
        $this->newLine();
        $this->warn('Master testing may store all client profiles. Client production servers should only contain this client\'s profile and assets.');

        return self::SUCCESS;
    }
}
