<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OneApiPhase2InventoryCommand extends Command
{
    protected $signature = 'ota:one-api-phase-2-inventory';

    protected $description = 'Generate phase-2 One API file inventory and deployment manifests (read-only git classification).';

    public function handle(): int
    {
        $root = base_path();
        $status = trim((string) shell_exec('cd '.escapeshellarg($root).' && git status --short 2>nul'));
        $diffNames = trim((string) shell_exec('cd '.escapeshellarg($root).' && git diff --name-only HEAD 2>nul'));
        $untracked = trim((string) shell_exec('cd '.escapeshellarg($root).' && git ls-files --others --exclude-standard 2>nul'));

        $all = array_unique(array_filter(array_merge(
            preg_split('/\R/', $diffNames) ?: [],
            preg_split('/\R/', $untracked) ?: [],
        )));

        $runtime = [];
        $tests = [];
        $docs = [];
        $excluded = [];

        foreach ($all as $path) {
            $path = trim(str_replace('\\', '/', $path));
            if ($path === '') {
                continue;
            }
            $class = $this->classify($path);
            match ($class) {
                'runtime' => $runtime[] = $path,
                'test' => $tests[] = $path,
                'doc' => $docs[] = $path,
                default => $excluded[] = $path.':'.$class,
            };
        }

        sort($runtime);
        sort($tests);
        sort($docs);
        sort($excluded);

        $this->writeLines(storage_path('app/one-api-phase-2-runtime-files.txt'), $runtime);
        $this->writeLines(storage_path('app/one-api-phase-2-test-files.txt'), $tests);
        $this->writeLines(storage_path('app/one-api-phase-2-doc-files.txt'), $docs);
        $this->writeLines(storage_path('app/one-api-phase-2-excluded-files.txt'), $excluded);
        $this->writeDeployManifest($runtime);

        $this->info('Runtime files: '.count($runtime));
        $this->info('Test files: '.count($tests));
        $this->info('Doc files: '.count($docs));
        $this->info('Excluded/uncertain: '.count($excluded));

        return self::SUCCESS;
    }

    private function classify(string $path): string
    {
        if (str_starts_with($path, 'tests/') || str_contains($path, '/tests/')) {
            return 'test';
        }
        if (str_starts_with($path, 'docs/integrations/one-api/')) {
            return 'doc';
        }
        if (str_starts_with($path, 'UI_test/') || str_starts_with($path, 'storage/app/one-api-matrix')
            || str_ends_with($path, '.sftp') || str_starts_with($path, 'JETPK_')) {
            return 'artifact';
        }
        if (preg_match('#app/Services/Suppliers/OneApi|OneApi|one-api|one_api#i', $path)) {
            if (str_ends_with($path, '.php') && str_starts_with($path, 'app/')) {
                return 'runtime';
            }
            if (str_starts_with($path, 'public/js/ota-one-api')) {
                return 'runtime';
            }
            if (str_contains($path, 'resources/views') && str_contains($path, 'one-api')) {
                return 'runtime';
            }
        }
        if (in_array($path, [
            'app/Enums/SupplierProvider.php',
            'app/Services/Suppliers/SupplierAdapterResolver.php',
            'app/Services/Booking/BookingProviderRouter.php',
            'app/Services/Suppliers/SupplierBookingService.php',
            'app/Services/Suppliers/TicketingService.php',
            'app/Services/Suppliers/SupplierConnectionService.php',
            'app/Http/Controllers/Admin/SupplierConnectionController.php',
            'app/Support/Suppliers/OneApiSupplierConnectionNormalizer.php',
            'app/Support/OneApi/OneApiMutationCommandGate.php',
            'app/Support/Platform/PlatformModuleGate.php',
            'app/Support/Platform/PlatformModuleEnforcer.php',
            'app/Support/Platform/PlatformModuleRegistry.php',
            'app/Support/Bookings/SupplierLifecycleContextResolver.php',
            'app/Support/Suppliers/SupplierLifecycleCapabilities.php',
            'config/supplier_credentials.php',
            'config/suppliers.php',
            'config/ota-suppliers.php',
            'config/logging.php',
            'routes/web.php',
            'resources/views/dashboard/admin/api-settings/form.blade.php',
            'resources/views/dashboard/admin/api-settings/partials/supplier-panels/one_api.blade.php',
            'resources/views/frontend/booking/partials/passenger-details-body.blade.php',
        ], true)) {
            return 'runtime';
        }

        if (str_starts_with($path, 'app/Services/Suppliers/Sabre') || str_starts_with($path, 'app/Support/Sabre')) {
            return 'unrelated_sabre';
        }

        return 'uncertain';
    }

    /**
     * @param  list<string>  $lines
     */
    private function writeLines(string $path, array $lines): void
    {
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    /**
     * @param  list<string>  $runtime
     */
    private function writeDeployManifest(array $runtime): void
    {
        $remoteBase = '/home/pkjetp/jetpk_app';
        $sftp = ["lcd C:/Users/khadi/ota-jetpk"];
        $puts = [];
        foreach ($runtime as $file) {
            $sftp[] = 'put '.str_replace('\\', '/', $file).' '.$remoteBase.'/'.str_replace('\\', '/', $file);
            $puts[] = $file;
        }
        $this->writeLines(storage_path('app/one-api-deploy-files.txt'), $puts);
        $this->writeLines(storage_path('app/one-api-sftp-upload.txt'), $sftp);
        file_put_contents(storage_path('app/one-api-post-deploy-commands.sh'), implode(PHP_EOL, [
            '#!/bin/sh',
            '/opt/alt/php-fpm83/usr/bin/php artisan config:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan config:cache',
            '/opt/alt/php-fpm83/usr/bin/php artisan route:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan route:cache',
            '/opt/alt/php-fpm83/usr/bin/php artisan view:clear',
            '# php artisan migrate --force  # only if a One API migration was added',
            '/opt/alt/php-fpm83/usr/bin/php artisan ota:one-api-connection-audit --connection=<ID>',
        ]).PHP_EOL);
        $this->writeLines(storage_path('app/one-api-rollback-files.txt'), $puts);
        file_put_contents(storage_path('app/one-api-required-config.md'), "ONE_API_* env flags optional; connection credentials via admin API settings.\nsoap_url required for live SOAP.\n");
    }
}
